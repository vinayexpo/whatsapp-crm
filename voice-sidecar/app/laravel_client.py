import httpx

from app.config import settings


class LaravelClient:
    """Signs and sends callback requests to Laravel's internal API
    (backend/app/Http/Middleware/VerifyInternalServiceSecret.php)."""

    def __init__(self) -> None:
        self._base_url = settings.laravel_base_url.rstrip("/")
        self._headers = {"X-Internal-Secret": settings.laravel_shared_secret}

    async def sdp_answer(self, whatsapp_call_id: str, sdp: str) -> None:
        async with httpx.AsyncClient() as client:
            response = await client.post(
                f"{self._base_url}/whatsapp-calls/{whatsapp_call_id}/sdp-answer",
                json={"sdp": sdp},
                headers=self._headers,
                timeout=15,
            )
            response.raise_for_status()

    async def next_prompt(self, whatsapp_call_id: str, speech: str = "") -> dict:
        async with httpx.AsyncClient() as client:
            response = await client.post(
                f"{self._base_url}/whatsapp-calls/{whatsapp_call_id}/next-prompt",
                json={"speech": speech},
                headers=self._headers,
                timeout=15,
            )
            response.raise_for_status()
            return response.json()

    async def session_event(self, whatsapp_call_id: str, sidecar_session_id: str) -> None:
        async with httpx.AsyncClient() as client:
            response = await client.post(
                f"{self._base_url}/whatsapp-calls/{whatsapp_call_id}/session-event",
                json={"sidecar_session_id": sidecar_session_id},
                headers=self._headers,
                timeout=15,
            )
            response.raise_for_status()

    async def spoken(self, whatsapp_call_id: str, text: str) -> None:
        async with httpx.AsyncClient() as client:
            response = await client.post(
                f"{self._base_url}/whatsapp-calls/{whatsapp_call_id}/spoken",
                json={"text": text},
                headers=self._headers,
                timeout=15,
            )
            response.raise_for_status()
