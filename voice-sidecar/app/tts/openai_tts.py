from typing import AsyncIterator

import httpx
import numpy as np
from scipy.signal import resample_poly

from app.webrtc import SAMPLE_RATE

# OpenAI-compatible /audio/speech endpoints return PCM as 16-bit mono @
# 24kHz when response_format=pcm is requested -- no WAV header to parse.
PROVIDER_PCM_SAMPLE_RATE = 24000
REQUEST_TIMEOUT_SECONDS = 30.0


class OpenAiTtsProvider:
    """Calls an OpenAI-compatible /audio/speech endpoint. base_url/api_key/
    model/voice are resolved per-call (per-company AiAssistantSetting),
    never configured once at process startup -- there is no local model to
    warm up."""

    async def stream(
        self,
        text: str,
        base_url: str,
        api_key: str | None,
        model: str,
        voice: str,
    ) -> AsyncIterator[bytes]:
        headers = {"Authorization": f"Bearer {api_key}"} if api_key else {}

        async with httpx.AsyncClient(timeout=REQUEST_TIMEOUT_SECONDS) as client:
            response = await client.post(
                f"{base_url.rstrip('/')}/audio/speech",
                headers=headers,
                json={
                    "model": model,
                    "voice": voice,
                    "input": text,
                    "response_format": "pcm",
                },
            )
            response.raise_for_status()
            pcm_bytes = response.content

        pcm = np.frombuffer(pcm_bytes, dtype=np.int16)

        if PROVIDER_PCM_SAMPLE_RATE != SAMPLE_RATE:
            # resample_poly silently returns all-zero output for int16 input
            # (integer-domain FIR filtering underflows to 0) -- must resample
            # in float before converting back to int16.
            pcm = resample_poly(pcm.astype(np.float32), SAMPLE_RATE, PROVIDER_PCM_SAMPLE_RATE).astype(np.int16)

        yield pcm.tobytes()
