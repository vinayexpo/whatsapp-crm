import asyncio
import base64
import fractions
import hashlib
import hmac
import logging
import time

import numpy as np
from aiortc import RTCConfiguration, RTCIceServer, RTCPeerConnection, RTCSessionDescription
from aiortc.mediastreams import AudioStreamTrack, MediaStreamError
from av import AudioFrame
from scipy.signal import resample_poly

from app.config import settings

logger = logging.getLogger("voice_sidecar")

SAMPLE_RATE = 48000
SAMPLES_PER_FRAME = 960  # 20ms @ 48kHz


class TtsAudioTrack(AudioStreamTrack):
    """An outbound audio track fed by pushing raw PCM16 mono bytes, which
    this track re-frames into 20ms Opus-ready AudioFrames for aiortc."""

    def __init__(self) -> None:
        super().__init__()
        self._queue: asyncio.Queue[bytes | None] = asyncio.Queue()
        self._buffer = b""
        self._pts = 0
        self._start_time: float | None = None

    def push_pcm(self, chunk: bytes) -> None:
        self._queue.put_nowait(chunk)

    def end_utterance(self) -> None:
        self._queue.put_nowait(None)

    async def recv(self) -> AudioFrame:
        frame_bytes = SAMPLES_PER_FRAME * 2  # 16-bit mono

        while len(self._buffer) < frame_bytes:
            chunk = await self._queue.get()
            if chunk is None:
                self._buffer += b"\x00" * (frame_bytes - len(self._buffer))
                break
            self._buffer += chunk

        frame_data, self._buffer = self._buffer[:frame_bytes], self._buffer[frame_bytes:]
        samples = np.frombuffer(frame_data, dtype=np.int16).reshape(1, -1)

        frame = AudioFrame.from_ndarray(samples, format="s16", layout="mono")
        frame.sample_rate = SAMPLE_RATE
        frame.pts = self._pts
        frame.time_base = fractions.Fraction(1, SAMPLE_RATE)

        # Pace frames to real time (20ms apart), matching aiortc's own
        # AudioStreamTrack.recv() base-class pacing, which this override
        # otherwise bypasses entirely -- without this, an utterance's PCM
        # is queued near-instantly and every frame is emitted back-to-back,
        # arriving far faster than realtime and getting dropped by the
        # receiver's jitter buffer instead of being heard.
        if self._start_time is None:
            self._start_time = time.time()
        else:
            target = self._start_time + (self._pts / SAMPLE_RATE)
            wait = target - time.time()
            if wait > 0:
                await asyncio.sleep(wait)

        self._pts += SAMPLES_PER_FRAME

        return frame


def build_ice_servers() -> list[RTCIceServer]:
    if not settings.coturn_host or not settings.coturn_secret:
        return [RTCIceServer(urls="stun:stun.l.google.com:19302")]

    # coturn is started with --use-auth-secret, i.e. the time-limited REST API
    # credential scheme: username must be "<expiry-unix-ts>:<userid>" and the
    # password is HMAC-SHA1(secret, username), base64-encoded.
    expiry = int(time.time()) + 3600
    username = f"{expiry}:voice-sidecar"
    digest = hmac.new(settings.coturn_secret.encode(), username.encode(), hashlib.sha1).digest()
    credential = base64.b64encode(digest).decode()

    return [RTCIceServer(
        urls=f"turn:{settings.coturn_host}:3478",
        username=username,
        credential=credential,
    )]


class CallSession:
    def __init__(self, whatsapp_call_id: str, on_inbound_frame=None) -> None:
        self.whatsapp_call_id = whatsapp_call_id
        self.pc = RTCPeerConnection(configuration=RTCConfiguration(iceServers=build_ice_servers()))
        self.audio_track = TtsAudioTrack()
        self.pc.addTrack(self.audio_track)
        self._on_inbound_frame = on_inbound_frame
        self._inbound_task: asyncio.Task | None = None

        @self.pc.on("iceconnectionstatechange")
        def on_ice_state_change() -> None:
            logger.info("call %s: ICE connection state -> %s", whatsapp_call_id, self.pc.iceConnectionState)

        @self.pc.on("connectionstatechange")
        def on_connection_state_change() -> None:
            logger.info("call %s: peer connection state -> %s", whatsapp_call_id, self.pc.connectionState)

        @self.pc.on("track")
        def on_track(track) -> None:
            logger.info("call %s: received inbound track kind=%s", whatsapp_call_id, track.kind)
            if track.kind == "audio" and self._on_inbound_frame is not None:
                self._inbound_task = asyncio.ensure_future(self._consume_inbound_audio(track))

    async def _consume_inbound_audio(self, track) -> None:
        buffer = b""
        frame_bytes = SAMPLES_PER_FRAME * 2  # 16-bit mono @ 48kHz
        frames_received = 0

        logger.info("call %s: waiting for first inbound audio frame", self.whatsapp_call_id)

        while True:
            try:
                frame = await track.recv()
            except MediaStreamError:
                logger.info("call %s: inbound track ended after %d frames", self.whatsapp_call_id, frames_received)
                break

            frames_received += 1
            if frames_received == 1:
                logger.info("call %s: received first inbound audio frame", self.whatsapp_call_id)

            samples = frame.to_ndarray()

            # PyAV returns packed multi-channel s16 as a single interleaved
            # row -- shape (1, N*channels), e.g. (1, 1920) for a 960-sample
            # stereo frame -- NOT one row per channel. samples.mean(axis=0)
            # is a no-op on that shape and silently leaves L/R interleaved
            # into what downstream code treats as a mono PCM stream, which
            # is garbage audio (and explains VAD misfiring as "speech"
            # nonstop). Deinterleave by channel count before downmixing.
            channels = len(frame.layout.channels)
            if channels > 1:
                samples = samples.reshape(-1, channels).T.mean(axis=0)
            else:
                samples = samples.reshape(-1)

            # aiortc's OpusDecoder emits s16 frames today, but to_ndarray()'s
            # dtype depends on frame.format -- guard against a float-format
            # frame (range [-1, 1]) being truncated straight to int16
            # (which would collapse to near-silence) instead of assuming s16.
            was_float = np.issubdtype(samples.dtype, np.floating)
            samples = samples.astype(np.float32)
            if was_float:
                samples = samples * 32768.0

            if frame.sample_rate != SAMPLE_RATE:
                samples = resample_poly(samples, SAMPLE_RATE, frame.sample_rate)

            pcm = samples.astype(np.int16).tobytes()
            buffer += pcm

            if frames_received % 250 == 0:
                logger.info(
                    "call %s: inbound audio frames=%d format=%s sample_rate=%d min=%d max=%d",
                    self.whatsapp_call_id, frames_received, frame.format.name, frame.sample_rate,
                    int(samples.min()) if samples.size else 0, int(samples.max()) if samples.size else 0,
                )

            while len(buffer) >= frame_bytes:
                chunk, buffer = buffer[:frame_bytes], buffer[frame_bytes:]
                self._on_inbound_frame(chunk)

    async def accept_offer(self, sdp_offer: str) -> str:
        offer = RTCSessionDescription(sdp=sdp_offer, type="offer")
        await self.pc.setRemoteDescription(offer)

        for t in self.pc.getTransceivers():
            logger.info(
                "call %s: transceiver kind=%s direction=%s currentDirection=%s",
                self.whatsapp_call_id, t.kind, t.direction, t.currentDirection,
            )

        answer = await self.pc.createAnswer()
        await self.pc.setLocalDescription(answer)

        logger.info("call %s: SDP answer created", self.whatsapp_call_id)
        logger.info("call %s: offer sdp=\n%s", self.whatsapp_call_id, sdp_offer)
        logger.info("call %s: answer sdp=\n%s", self.whatsapp_call_id, self.pc.localDescription.sdp)

        return self.pc.localDescription.sdp

    async def close(self) -> None:
        if self._inbound_task is not None:
            self._inbound_task.cancel()
        await self.pc.close()
