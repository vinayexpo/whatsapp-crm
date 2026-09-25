from typing import AsyncIterator, Protocol


class TtsProvider(Protocol):
    """Streams synthesized speech audio for a text prompt as raw PCM chunks
    (16-bit, 48kHz, mono) suitable for encoding onto an outbound RTP track."""

    async def stream(self, text: str, voice_id: str | None = None) -> AsyncIterator[bytes]:
        ...
