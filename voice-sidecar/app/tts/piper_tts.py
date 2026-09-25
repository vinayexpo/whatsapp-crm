import asyncio
from typing import AsyncIterator

import numpy as np
from piper.voice import PiperVoice
from scipy.signal import resample_poly

from app.config import settings
from app.webrtc import SAMPLE_RATE

DEFAULT_MODEL_PATH = "/app/voices/en_US-lessac-medium.onnx"


class PiperTtsProvider:
    def __init__(self) -> None:
        self._voice = PiperVoice.load(settings.piper_model_path or DEFAULT_MODEL_PATH)

    async def stream(self, text: str, voice_id: str | None = None) -> AsyncIterator[bytes]:
        loop = asyncio.get_running_loop()
        chunks: list[bytes] = await loop.run_in_executor(None, self._synthesize, text)

        for chunk in chunks:
            yield chunk

    def _synthesize(self, text: str) -> list[bytes]:
        chunks = []
        source_rate = self._voice.config.sample_rate

        for audio_bytes in self._voice.synthesize_stream_raw(text):
            pcm = np.frombuffer(audio_bytes, dtype=np.int16)

            if source_rate != SAMPLE_RATE:
                # resample_poly silently returns all-zero output for int16 input
                # (integer-domain FIR filtering underflows to 0) -- must resample
                # in float before converting back to int16.
                pcm = resample_poly(pcm.astype(np.float32), SAMPLE_RATE, source_rate).astype(np.int16)

            chunks.append(pcm.tobytes())

        return chunks
