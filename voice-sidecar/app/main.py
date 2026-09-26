import asyncio
import logging

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel

from app.laravel_client import LaravelClient
from app.stt import UtteranceCollector
from app.tts.piper_tts import PiperTtsProvider
from app.webrtc import SAMPLE_RATE, CallSession

logging.basicConfig(level=logging.INFO)
logging.getLogger("aiortc").setLevel(logging.INFO)
logging.getLogger("aioice").setLevel(logging.INFO)
logger = logging.getLogger("voice_sidecar")

app = FastAPI()
laravel = LaravelClient()
tts = PiperTtsProvider()


def _handle_asyncio_exception(loop: asyncio.AbstractEventLoop, context: dict) -> None:
    exc = context.get("exception")
    # aioice's STUN retry timer can fire after a transaction's future is
    # already resolved (response arrived just as the retry timeout expired),
    # which raises InvalidStateError from inside a call_later callback with
    # no task/future to propagate to. It's a benign race in RFC 7675 consent
    # freshness checks, not a call-affecting failure -- log it quietly
    # instead of letting the default handler report it as an error.
    if isinstance(exc, asyncio.InvalidStateError) and "Transaction.__retry" in context.get("message", ""):
        logger.debug("benign aioice STUN transaction race: %s", context["message"])
        return
    loop.default_exception_handler(context)


@app.on_event("startup")
async def _install_exception_handler() -> None:
    asyncio.get_event_loop().set_exception_handler(_handle_asyncio_exception)

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
    if session.pc.connectionState in ("failed", "closed"):
        logger.warning(
            "call %s: skipping speak(), peer connection state is %s (nothing would be heard)",
            session.whatsapp_call_id, session.pc.connectionState,
        )
        return

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


async def handle_caller_utterance(session: CallSession, voice_id: str | None, text: str) -> None:
    logger.info("call %s: caller said %r", session.whatsapp_call_id, text)

    try:
        result = await laravel.next_prompt(session.whatsapp_call_id, text)
    except Exception:
        logger.exception("call %s: next-prompt request failed", session.whatsapp_call_id)
        return

    prompt = result.get("prompt")
    if prompt:
        await speak(session, prompt, voice_id)

    if result.get("action") == "terminate":
        session_obj = sessions.pop(session.whatsapp_call_id, None)
        if session_obj:
            await session_obj.close()


@app.post("/sessions")
async def create_session(payload: CreateSessionRequest) -> dict:
    collector_holder: dict[str, UtteranceCollector] = {}

    def on_inbound_frame(frame_bytes: bytes) -> None:
        collector_holder["collector"].push_frame(frame_bytes)

    session = CallSession(payload.whatsapp_call_id, on_inbound_frame=on_inbound_frame)
    sessions[payload.whatsapp_call_id] = session

    async def on_utterance(text: str) -> None:
        await handle_caller_utterance(session, payload.tts_voice_id, text)

    collector_holder["collector"] = UtteranceCollector(on_utterance)

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
