import asyncio
import logging
import time

import numpy as np
import sherpa_onnx
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

# On this host, transcribing a single short utterance with the previous
# engine (faster-whisper) could take 5-30+ seconds under load, so a stale
# queued utterance could reach the front of the line long after the caller
# hung up. sherpa-onnx's SenseVoice model is much lighter, but keep the same
# staleness guard as a safety net rather than assuming the new engine is
# always fast enough under contention.
MAX_UTTERANCE_AGE_SECONDS = 8.0

SENSE_VOICE_MODEL_DIR = "/app/stt-models/sense-voice"

# SenseVoice doesn't pad/force audio into a fixed 30s window the way Whisper
# does, so it's far less prone to hallucinating stock phrases on short
# silence/noise clips -- but it also exposes no per-segment confidence score
# to gate on. The cheap remaining guard is text length: a hallucinated/
# garbage decode on a very short utterance tends to produce a handful of
# characters (or nothing); real speech this short still reliably produces at
# least one word.
MIN_TEXT_CHARS = 2

_recognizer: sherpa_onnx.OfflineRecognizer | None = None


def _get_recognizer() -> sherpa_onnx.OfflineRecognizer:
    global _recognizer
    if _recognizer is None:
        _recognizer = sherpa_onnx.OfflineRecognizer.from_sense_voice(
            model=f"{SENSE_VOICE_MODEL_DIR}/model.onnx",
            tokens=f"{SENSE_VOICE_MODEL_DIR}/tokens.txt",
            num_threads=2,
            sample_rate=STT_SAMPLE_RATE,
            language="en",
            use_itn=True,
            provider="cpu",
        )
    return _recognizer


def warm_up_model() -> None:
    """Blocking; call once at process startup (in an executor) so the model
    weight load happens before any real call, not during one."""
    _get_recognizer()


def transcribe_pcm48k(pcm: bytes) -> str:
    """Blocking; run in an executor. pcm is 16-bit mono @ 48kHz."""
    samples = np.frombuffer(pcm, dtype=np.int16).astype(np.float32) / 32768.0
    resampled = resample_poly(samples, STT_SAMPLE_RATE, SAMPLE_RATE).astype(np.float32)

    recognizer = _get_recognizer()
    stream = recognizer.create_stream()
    stream.accept_waveform(STT_SAMPLE_RATE, resampled)
    recognizer.decode_stream(stream)
    text = stream.result.text.strip()

    accepted = len(text) >= MIN_TEXT_CHARS
    logger.info("transcribe: text=%r accepted=%s", text, accepted)

    return text if accepted else ""


class UtteranceCollector:
    """Consumes 20ms PCM16 mono @ 48kHz frames from the caller's inbound
    audio track, uses WebRTC VAD to detect speech vs silence, and calls
    on_utterance(text) once a trailing silence closes out a spoken segment."""

    def __init__(self, on_utterance, cpu_lock: asyncio.Lock | None = None) -> None:
        self._vad = webrtcvad.Vad(2)
        self._on_utterance = on_utterance
        self._cpu_lock = cpu_lock or asyncio.Lock()
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

        is_speech = self._vad.is_speech(frame_bytes, SAMPLE_RATE)

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

                loop = asyncio.get_running_loop()
                text = await loop.run_in_executor(None, transcribe_pcm48k, pcm)
        except Exception:
            logger.exception("failed to transcribe caller utterance")
            return

        if text:
            await self._on_utterance(text)
