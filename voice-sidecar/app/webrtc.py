import asyncio
import base64
import fractions
import hashlib
import hmac
import logging
import time

import numpy as np
from aiortc import RTCConfiguration, RTCIceServer, RTCPeerConnection, RTCSessionDescription
from aiortc.mediastreams import AudioStreamTrack
from av import AudioFrame

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
    def __init__(self, whatsapp_call_id: str) -> None:
        self.whatsapp_call_id = whatsapp_call_id
        self.pc = RTCPeerConnection(configuration=RTCConfiguration(iceServers=build_ice_servers()))
        self.audio_track = TtsAudioTrack()
        self.pc.addTrack(self.audio_track)

        @self.pc.on("iceconnectionstatechange")
        def on_ice_state_change() -> None:
            logger.info("call %s: ICE connection state -> %s", whatsapp_call_id, self.pc.iceConnectionState)

        @self.pc.on("connectionstatechange")
        def on_connection_state_change() -> None:
            logger.info("call %s: peer connection state -> %s", whatsapp_call_id, self.pc.connectionState)

    async def accept_offer(self, sdp_offer: str) -> str:
        offer = RTCSessionDescription(sdp=sdp_offer, type="offer")
        await self.pc.setRemoteDescription(offer)

        answer = await self.pc.createAnswer()
        await self.pc.setLocalDescription(answer)

        logger.info("call %s: SDP answer created", self.whatsapp_call_id)

        return self.pc.localDescription.sdp

    async def close(self) -> None:
        await self.pc.close()
