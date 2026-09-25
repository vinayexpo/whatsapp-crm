from typing import AsyncIterator

from openai import AsyncOpenAI

from app.config import settings

DEFAULT_VOICE = "alloy"


class OpenAiTtsProvider:
    def __init__(self) -> None:
        self._client = AsyncOpenAI(api_key=settings.openai_api_key)

    async def stream(self, text: str, voice_id: str | None = None) -> AsyncIterator[bytes]:
        async with self._client.audio.speech.with_streaming_response.create(
            model="tts-1",
            voice=voice_id or DEFAULT_VOICE,
            input=text,
            response_format="pcm",
        ) as response:
            async for chunk in response.iter_bytes(chunk_size=4096):
                yield chunk
