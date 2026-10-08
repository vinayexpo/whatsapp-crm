import asyncio
import io
import logging
import time
import wave

import httpx
import numpy as np
import webrtcvad
from scipy.signal import resample_poly

from app.webrtc import SAMPLE_RATE

logger = logging.getLogger("voice_sidecar")

VAD_FRAME_MS = 20
VAD_FRAME_SAMPLES = SAMPLE_RATE * VAD_FRAME_MS // 1000  # 960 @ 48kHz
# 700ms was short enough that a single breath mid-sentence ("Hello... Hello.")
# split into separate utterances, each paying the STT engine's full per-call
# inference overhead on this slow 2-core host instead of being transcribed
# together once. Widening the gap trades a little end-of-utterance latency
# for far fewer, larger transcription calls.
SILENCE_MS_TO_END_UTTERANCE = 1200
SILENCE_FRAMES_TO_END_UTTERANCE = SILENCE_MS_TO_END_UTTERANCE // VAD_FRAME_MS
MIN_UTTERANCE_MS = 600
MIN_UTTERANCE_FRAMES = MIN_UTTERANCE_MS // VAD_FRAME_MS
STT_SAMPLE_RATE = 16000

# webrtcvad is purely energy-based and, on real WhatsApp calls, line/mic
# noise floor sometimes sits just high enough that even its most aggressive
# mode (3) classifies it as continuous "speech" forever -- observed live as
# speech_frames tracking ~85-90% of all frames for the full duration of a
# call where the caller's actual voice should have produced clear gaps, with
# RMS amplitude never exceeding ~200 (real speech picked up by a phone mic
# typically peaks in the thousands). Gate frames below this RMS floor as
# silence before they ever reach the VAD, so quiet background noise can't
# masquerade as an unbroken utterance that never flushes.
MIN_SPEECH_RMS = 300

# A caller may have hung up long before a slow-to-process utterance reaches
# the front of the shared cpu/HTTP queue -- transcribing and replying to it
# at that point is pure waste and risks pushing a reply into a dead call.
MAX_UTTERANCE_AGE_SECONDS = 8.0

# A hallucinated/garbage decode on a very short noise clip tends to produce a
# handful of characters (or nothing); real speech this short still reliably
# produces at least one word. Cheap guard independent of provider confidence
# scores, which OpenAI-compatible transcription APIs don't expose per call.
MIN_TEXT_CHARS = 2

REQUEST_TIMEOUT_SECONDS = 20.0


def _pcm48k_to_wav16k(pcm: bytes) -> bytes:
    samples = np.frombuffer(pcm, dtype=np.int16).astype(np.float32) / 32768.0
    resampled = resample_poly(samples, STT_SAMPLE_RATE, SAMPLE_RATE)
    pcm16 = np.clip(resampled * 32768.0, -32768, 32767).astype(np.int16)

    buffer = io.BytesIO()
    with wave.open(buffer, "wb") as wav_file:
        wav_file.setnchannels(1)
        wav_file.setsampwidth(2)
        wav_file.setframerate(STT_SAMPLE_RATE)
        wav_file.writeframes(pcm16.tobytes())

    return buffer.getvalue()


async def transcribe_pcm48k(
    pcm: bytes,
    base_url: str,
    api_key: str | None,
    model: str,
    language: str | None = None,
) -> str:
    """pcm is 16-bit mono @ 48kHz. Posts to an OpenAI-compatible
    /audio/transcriptions endpoint and returns the transcript, or "" if the
    provider errored or returned a too-short/empty result."""
    wav_bytes = _pcm48k_to_wav16k(pcm)

    data = {"model": model}
    if language:
        data["language"] = language

    headers = {"Authorization": f"Bearer {api_key}"} if api_key else {}

    try:
        async with httpx.AsyncClient(timeout=REQUEST_TIMEOUT_SECONDS) as client:
            response = await client.post(
                f"{base_url.rstrip('/')}/audio/transcriptions",
                headers=headers,
                data=data,
                files={"file": ("utterance.wav", wav_bytes, "audio/wav")},
            )
            response.raise_for_status()
            text = response.json().get("text", "").strip()
    except Exception:
        logger.exception("transcribe: request to STT provider failed")
        return ""

    accepted = len(text) >= MIN_TEXT_CHARS
    logger.info("transcribe: text=%r accepted=%s", text, accepted)

    return text if accepted else ""


class UtteranceCollector:
    """Consumes 20ms PCM16 mono @ 48kHz frames from the caller's inbound
    audio track, uses WebRTC VAD to detect speech vs silence, and calls
    on_utterance(text) once a trailing silence closes out a spoken segment."""

    def __init__(
        self,
        on_utterance,
        stt_base_url: str,
        stt_api_key: str | None,
        stt_model: str,
        cpu_lock: asyncio.Lock | None = None,
        language: str | None = None,
    ) -> None:
        self._vad = webrtcvad.Vad(3)
        self._on_utterance = on_utterance
        self._stt_base_url = stt_base_url
        self._stt_api_key = stt_api_key
        self._stt_model = stt_model
        self._cpu_lock = cpu_lock or asyncio.Lock()
        self._language = language
        self._speech_frames: list[bytes] = []
        self._silence_run = 0
        self._in_speech = False
        self._frames_seen = 0
        self._frames_wrong_size = 0
        self._speech_frames_seen = 0

    def push_frame(self, frame_bytes: bytes) -> None:
        self._frames_seen += 1
        if self._frames_seen % 250 == 0:
            logger.info(
                "utterance collector: frames_seen=%d wrong_size=%d speech_frames=%d in_speech=%s",
                self._frames_seen, self._frames_wrong_size, self._speech_frames_seen, self._in_speech,
            )

        if len(frame_bytes) != VAD_FRAME_SAMPLES * 2:
            self._frames_wrong_size += 1
            return

        samples = np.frombuffer(frame_bytes, dtype=np.int16).astype(np.float32)
        rms = float(np.sqrt(np.mean(samples * samples))) if samples.size else 0.0

        is_speech = rms >= MIN_SPEECH_RMS and self._vad.is_speech(frame_bytes, SAMPLE_RATE)

        if is_speech:
            self._speech_frames_seen += 1
            self._speech_frames.append(frame_bytes)
            self._silence_run = 0
            self._in_speech = True
            return

        if not self._in_speech:
            return

        self._silence_run += 1
        self._speech_frames.append(frame_bytes)

        if self._silence_run >= SILENCE_FRAMES_TO_END_UTTERANCE:
            self._flush()

    def _flush(self) -> None:
        frames, self._speech_frames = self._speech_frames, []
        self._in_speech = False
        self._silence_run = 0

        if len(frames) < MIN_UTTERANCE_FRAMES:
            logger.info("utterance collector: discarding short utterance of %d frames", len(frames))
            return

        logger.info("utterance collector: flushing utterance of %d frames for transcription", len(frames))
        pcm = b"".join(frames)
        asyncio.get_event_loop().create_task(self._transcribe_and_emit(pcm, time.monotonic()))

    async def _transcribe_and_emit(self, pcm: bytes, flushed_at: float) -> None:
        try:
            async with self._cpu_lock:
                # This utterance may have been waiting behind other queued
                # STT/TTS work for many seconds on this slow host -- if the
                # caller has plausibly already hung up by now, transcribing
                # and replying is pure waste (and risks pushing audio into a
                # dead peer connection). Skip stale work instead of always
                # running it.
                age = time.monotonic() - flushed_at
                if age > MAX_UTTERANCE_AGE_SECONDS:
                    logger.info("utterance collector: dropping stale utterance, age=%.1fs", age)
                    return

                text = await transcribe_pcm48k(
                    pcm, self._stt_base_url, self._stt_api_key, self._stt_model, self._language,
                )
        except Exception:
            logger.exception("failed to transcribe caller utterance")
            return

        if text:
            await self._on_utterance(text)
