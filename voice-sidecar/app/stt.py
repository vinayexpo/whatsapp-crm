import asyncio
import logging
import time

import numpy as np
import webrtcvad
from moonshine_onnx import MoonshineOnnxModel, load_tokenizer
from scipy.signal import resample_poly

from app.webrtc import SAMPLE_RATE

logger = logging.getLogger("voice_sidecar")

VAD_FRAME_MS = 20
VAD_FRAME_SAMPLES = SAMPLE_RATE * VAD_FRAME_MS // 1000  # 960 @ 48kHz
# 700ms was short enough that a single breath mid-sentence ("Hello... Hello.")
# split into separate utterances, each paying Whisper's full per-call
# inference overhead on this slow 2-core host instead of being transcribed
# together once. Widening the gap trades a little end-of-utterance latency
# for far fewer, larger transcription calls.
SILENCE_MS_TO_END_UTTERANCE = 1200
SILENCE_FRAMES_TO_END_UTTERANCE = SILENCE_MS_TO_END_UTTERANCE // VAD_FRAME_MS
MIN_UTTERANCE_MS = 600
MIN_UTTERANCE_FRAMES = MIN_UTTERANCE_MS // VAD_FRAME_MS
MOONSHINE_SAMPLE_RATE = 16000

# On this host, transcribing a single short utterance can take 5-30+ seconds
# (observed live: a 5s utterance took ~12s, and four utterances queued back
# to back left the caller answered ~34s after they first spoke). By the time
# a stale queued utterance would reach the front of the line, the caller has
# often already hung up. Drop anything that's been waiting longer than this
# instead of speaking a reply into a call that's likely already over.
MAX_UTTERANCE_AGE_SECONDS = 8.0

# Moonshine doesn't pad/force audio into a fixed 30s window the way Whisper
# does, so it rarely hallucinates stock phrases on short silence/noise clips
# the way faster-whisper did -- but it also exposes no per-segment
# no_speech_prob/avg_logprob to gate on. The cheap remaining guard is text
# length: a hallucinated/garbage decode on a very short utterance tends to
# produce a handful of characters (or nothing); real speech this short still
# reliably produces at least one word.
MIN_TEXT_CHARS = 2

MOONSHINE_MODEL_NAME = "moonshine/base"

_model: MoonshineOnnxModel | None = None
_tokenizer = None


def _get_model() -> tuple[MoonshineOnnxModel, object]:
    global _model, _tokenizer
    if _model is None:
        _model = MoonshineOnnxModel(model_name=MOONSHINE_MODEL_NAME)
        _tokenizer = load_tokenizer()
    return _model, _tokenizer


def warm_up_model() -> None:
    """Blocking; call once at process startup (in an executor) so the model
    weight load happens before any real call, not during one."""
    _get_model()


def transcribe_pcm48k(pcm: bytes) -> str:
    """Blocking; run in an executor. pcm is 16-bit mono @ 48kHz."""
    samples = np.frombuffer(pcm, dtype=np.int16).astype(np.float32) / 32768.0
    resampled = resample_poly(samples, MOONSHINE_SAMPLE_RATE, SAMPLE_RATE).astype(np.float32)

    model, tokenizer = _get_model()
    tokens = model.generate(resampled[np.newaxis, :].astype(np.float32))
    text = tokenizer.decode_batch(tokens)[0].strip()

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
