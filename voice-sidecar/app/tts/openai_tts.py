import struct
from typing import AsyncIterator

import httpx
import numpy as np
from scipy.signal import resample_poly

from app.webrtc import SAMPLE_RATE

# Providers return 16-bit mono PCM, but not all of them support
# response_format=pcm (e.g. Groq only accepts "wav"), and the sample rate
# isn't guaranteed to be the same across providers -- request WAV uniformly
# and read the actual rate out of its fmt chunk instead of assuming one.
REQUEST_TIMEOUT_SECONDS = 30.0


def _parse_wav(wav_bytes: bytes) -> tuple[bytes, int]:
    """Returns (pcm_bytes, sample_rate) from a RIFF/WAVE byte string.
    Some providers stream WAV with a placeholder/garbage size in the
    RIFF and data chunk headers, so this scans chunk-by-chunk rather than
    trusting those sizes -- the data chunk is taken as "everything after
    its header" instead of trusting its declared length."""
    if wav_bytes[:4] != b"RIFF" or wav_bytes[8:12] != b"WAVE":
        raise ValueError("TTS response is not a WAV file")

    sample_rate = None
    pos = 12
    while pos + 8 <= len(wav_bytes):
        chunk_id = wav_bytes[pos:pos + 4]
        chunk_size = struct.unpack("<I", wav_bytes[pos + 4:pos + 8])[0]
        body_start = pos + 8

        if chunk_id == b"fmt ":
            sample_rate = struct.unpack("<I", wav_bytes[body_start + 4:body_start + 8])[0]
            pos = body_start + chunk_size
        elif chunk_id == b"data":
            if sample_rate is None:
                raise ValueError("WAV data chunk arrived before fmt chunk")
            return wav_bytes[body_start:], sample_rate
        else:
            pos = body_start + chunk_size

    raise ValueError("WAV data chunk not found")


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
        tts_url: str | None = None,
    ) -> AsyncIterator[bytes]:
        headers = {"Authorization": f"Bearer {api_key}"} if api_key else {}
        url = tts_url or f"{base_url.rstrip('/')}/audio/speech"

        async with httpx.AsyncClient(timeout=REQUEST_TIMEOUT_SECONDS) as client:
            response = await client.post(
                url,
                headers=headers,
                json={
                    "model": model,
                    "voice": voice,
                    "input": text,
                    "response_format": "wav",
                },
            )
            response.raise_for_status()
            pcm_bytes, provider_sample_rate = _parse_wav(response.content)

        pcm = np.frombuffer(pcm_bytes, dtype=np.int16)

        if provider_sample_rate != SAMPLE_RATE:
            # resample_poly silently returns all-zero output for int16 input
            # (integer-domain FIR filtering underflows to 0) -- must resample
            # in float before converting back to int16.
            pcm = resample_poly(pcm.astype(np.float32), SAMPLE_RATE, provider_sample_rate).astype(np.int16)

        yield pcm.tobytes()
