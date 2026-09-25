import asyncio
import logging

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel

from app.laravel_client import LaravelClient
from app.tts.piper_tts import PiperTtsProvider
from app.webrtc import SAMPLE_RATE, CallSession

logging.basicConfig(level=logging.INFO)
logging.getLogger("aiortc").setLevel(logging.INFO)
logging.getLogger("aioice").setLevel(logging.INFO)
logger = logging.getLogger("voice_sidecar")

app = FastAPI()
laravel = LaravelClient()
tts = PiperTtsProvider()

sessions: dict[str, CallSession] = {}


class CreateSessionRequest(BaseModel):
    whatsapp_call_id: str
    meta_call_id: str
    sdp_offer: str
    greeting: str | None = None
    tts_voice_id: str | None = None
    callback_base_url: str | None = None


class SpeakRequest(BaseModel):
    text: str


async def speak(session: CallSession, text: str, voice_id: str | None) -> None:
    total_bytes = 0
    async for chunk in tts.stream(text, voice_id):
        total_bytes += len(chunk)
        session.audio_track.push_pcm(chunk)
    session.audio_track.end_utterance()
    logger.info("call %s: speak() pushed %d bytes of PCM for text=%r", session.whatsapp_call_id, total_bytes, text)

    try:
        await laravel.spoken(session.whatsapp_call_id, text)
    except Exception:
        logger.exception("call %s: failed to report spoken text to Laravel", session.whatsapp_call_id)

    # Give the paced track time to drain, then log actual outbound RTP stats
    # to confirm whether aiortc/DTLS-SRTP is really putting packets on the wire.
    await asyncio.sleep((total_bytes / 2 / SAMPLE_RATE) + 1)
    for stat in (await session.pc.getStats()).values():
        if stat.type == "outbound-rtp":
            logger.info(
                "call %s: outbound-rtp packetsSent=%s bytesSent=%s",
                session.whatsapp_call_id, getattr(stat, "packetsSent", None), getattr(stat, "bytesSent", None),
            )


@app.get("/healthz")
async def healthz() -> dict:
    return {"status": "ok"}


@app.post("/sessions")
async def create_session(payload: CreateSessionRequest) -> dict:
    session = CallSession(payload.whatsapp_call_id)
    sessions[payload.whatsapp_call_id] = session

    try:
        sdp_answer = await session.accept_offer(payload.sdp_offer)
        await laravel.sdp_answer(payload.whatsapp_call_id, sdp_answer)
        await laravel.session_event(payload.whatsapp_call_id, payload.whatsapp_call_id)

        if payload.greeting:
            await speak(session, payload.greeting, payload.tts_voice_id)
    except Exception:
        logger.exception("call %s: failed to set up session", payload.whatsapp_call_id)
        raise

    return {"status": "accepted"}


@app.post("/sessions/{whatsapp_call_id}/speak")
async def speak_prompt(whatsapp_call_id: str, payload: SpeakRequest) -> dict:
    session = sessions.get(whatsapp_call_id)

    if not session:
        raise HTTPException(status_code=404, detail="Unknown session")

    await speak(session, payload.text, None)

    return {"status": "ok"}


@app.delete("/sessions/{whatsapp_call_id}")
async def end_session(whatsapp_call_id: str) -> dict:
    session = sessions.pop(whatsapp_call_id, None)

    if session:
        await session.close()

    return {"status": "closed"}
