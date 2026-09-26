import asyncio
import logging

import numpy as np
import webrtcvad
from faster_whisper import WhisperModel
from scipy.signal import resample_poly

from app.webrtc import SAMPLE_RATE

logger = logging.getLogger("voice_sidecar")

VAD_FRAME_MS = 20
VAD_FRAME_SAMPLES = SAMPLE_RATE * VAD_FRAME_MS // 1000  # 960 @ 48kHz
SILENCE_MS_TO_END_UTTERANCE = 700
SILENCE_FRAMES_TO_END_UTTERANCE = SILENCE_MS_TO_END_UTTERANCE // VAD_FRAME_MS
MIN_UTTERANCE_MS = 600
MIN_UTTERANCE_FRAMES = MIN_UTTERANCE_MS // VAD_FRAME_MS
WHISPER_SAMPLE_RATE = 16000

# faster-whisper hallucinates stock phrases ("thank you", "thanks for
# watching") when fed silence/background noise that WebRTC VAD misclassified
# as speech. Reject segments with a high no-speech probability or low average
# confidence instead of trusting whatever text comes back.
MAX_NO_SPEECH_PROB = 0.6
MIN_AVG_LOGPROB = -1.0

_model: WhisperModel | None = None


def _get_model() -> WhisperModel:
    global _model
    if _model is None:
        _model = WhisperModel(
            "small.en",
            device="cpu",
            compute_type="int8",
            download_root="/app/whisper-models",
            local_files_only=True,
        )
    return _model


def transcribe_pcm48k(pcm: bytes) -> str:
    """Blocking; run in an executor. pcm is 16-bit mono @ 48kHz."""
    samples = np.frombuffer(pcm, dtype=np.int16).astype(np.float32) / 32768.0
    resampled = resample_poly(samples, WHISPER_SAMPLE_RATE, SAMPLE_RATE).astype(np.float32)

    # The WebRTC VAD in UtteranceCollector has already segmented this PCM
    # down to a single spoken utterance, so Whisper's own VAD pass is
    # redundant here -- and it was aggressively misclassifying genuine
    # speech as silence (observed dropping >90% of real caller audio),
    # leaving nothing for the confidence filters below to keep. The
    # no_speech_prob/avg_logprob checks below are the hallucination guard.
    segments, _info = _get_model().transcribe(resampled, language="en", vad_filter=False)

    kept = [
        segment.text.strip()
        for segment in segments
        if segment.no_speech_prob <= MAX_NO_SPEECH_PROB and segment.avg_logprob >= MIN_AVG_LOGPROB
    ]
    return " ".join(kept).strip()


class UtteranceCollector:
    """Consumes 20ms PCM16 mono @ 48kHz frames from the caller's inbound
    audio track, uses WebRTC VAD to detect speech vs silence, and calls
    on_utterance(text) once a trailing silence closes out a spoken segment."""

    def __init__(self, on_utterance) -> None:
        self._vad = webrtcvad.Vad(2)
        self._on_utterance = on_utterance
        self._speech_frames: list[bytes] = []
        self._silence_run = 0
        self._in_speech = False

    def push_frame(self, frame_bytes: bytes) -> None:
        if len(frame_bytes) != VAD_FRAME_SAMPLES * 2:
            return

        is_speech = self._vad.is_speech(frame_bytes, SAMPLE_RATE)

        if is_speech:
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
            return

        pcm = b"".join(frames)
        asyncio.get_event_loop().create_task(self._transcribe_and_emit(pcm))

    async def _transcribe_and_emit(self, pcm: bytes) -> None:
        try:
            loop = asyncio.get_running_loop()
            text = await loop.run_in_executor(None, transcribe_pcm48k, pcm)
        except Exception:
            logger.exception("failed to transcribe caller utterance")
            return

        if text:
            await self._on_utterance(text)
