import asyncio
from typing import AsyncIterator

import numpy as np
from piper.voice import PiperVoice
from scipy.signal import resample_poly

from app.webrtc import SAMPLE_RATE

VOICE_PATHS = {
    "en": "/app/voices/en_US-lessac-medium.onnx",
    "hi": "/app/voices/hi_IN-pratham-medium.onnx",
    "te": "/app/voices/te_IN-maya-medium.onnx",
}
DEFAULT_LANGUAGE = "en"


class PiperTtsProvider:
    def __init__(self) -> None:
        self._voices: dict[str, PiperVoice] = {}

    def _get_voice(self, language: str | None) -> PiperVoice:
        lang = language if language in VOICE_PATHS else DEFAULT_LANGUAGE

        if lang not in self._voices:
            self._voices[lang] = PiperVoice.load(VOICE_PATHS[lang])

        return self._voices[lang]

    def warm_up(self) -> None:
        for lang in VOICE_PATHS:
            self._get_voice(lang)

    async def stream(self, text: str, voice_id: str | None = None, language: str | None = None) -> AsyncIterator[bytes]:
        loop = asyncio.get_running_loop()
        chunks: list[bytes] = await loop.run_in_executor(None, self._synthesize, text, language)

        for chunk in chunks:
            yield chunk

    def _synthesize(self, text: str, language: str | None) -> list[bytes]:
        voice = self._get_voice(language)
        chunks = []
        source_rate = voice.config.sample_rate

        for audio_bytes in voice.synthesize_stream_raw(text):
            pcm = np.frombuffer(audio_bytes, dtype=np.int16)

            if source_rate != SAMPLE_RATE:
                # resample_poly silently returns all-zero output for int16 input
                # (integer-domain FIR filtering underflows to 0) -- must resample
                # in float before converting back to int16.
                pcm = resample_poly(pcm.astype(np.float32), SAMPLE_RATE, source_rate).astype(np.int16)

            chunks.append(pcm.tobytes())

        return chunks
