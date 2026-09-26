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

# The production host has only 2 CPU cores. Piper synthesis and Whisper
# transcription both run in the default thread executor, and both are CPU-
# bound enough to starve the event loop's real-time RTP pacing (asyncio.sleep
# calls in TtsAudioTrack.recv()) when they overlap -- observed live as a
# flushed transcription task starting 380ms before a greeting's speak() call,
# right when that greeting's audio was clipped on the receiving end even
# though our own packetsSent/bytesSent accounting looked complete. Serializing
# TTS and STT work through one lock keeps them from ever competing for the
# same two cores mid-call.
cpu_lock = asyncio.Lock()


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
    async with cpu_lock:
        async for chunk in tts.stream(text, voice_id):
            total_bytes += len(chunk)
            session.audio_track.push_pcm(chunk)
    session.audio_track.end_utterance()
    logger.info("call %s: speak() pushed %d bytes of PCM for text=%r", session.whatsapp_call_id, total_bytes, text)

    try:
        await laravel.spoken(session.whatsapp_call_id, text)
    except Exception:
        logger.exception("call %s: failed to report spoken text to Laravel", session.whatsapp_call_id)

    # There's no acoustic/network echo cancellation between our outbound TTS
    # and the inbound track, so while this audio is actually playing out
    # (and briefly after, for echo tail) the caller's device can loop it
    # right back to us and the VAD reads it as continuous "caller speech"
    # that never ends. Mute inbound frame delivery for the drain duration.
    session.is_speaking = True
    try:
        # Give the paced track time to drain, then log actual outbound RTP
        # stats to confirm whether aiortc/DTLS-SRTP is really putting
        # packets on the wire.
        await asyncio.sleep((total_bytes / 2 / SAMPLE_RATE) + 1)
    finally:
        session.is_speaking = False

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

    collector_holder["collector"] = UtteranceCollector(on_utterance, cpu_lock=cpu_lock)

    try:
        sdp_answer = await session.accept_offer(payload.sdp_offer)
        await laravel.sdp_answer(payload.whatsapp_call_id, sdp_answer)
        await laravel.session_event(payload.whatsapp_call_id, payload.whatsapp_call_id)

        if payload.greeting:
            # speak() paces outbound frames from wall-clock time starting at
            # the first recv() call, which aiortc can invoke while ICE/DTLS
            # is still negotiating. Pushing the greeting before the peer
            # connection is actually "connected" burns the pacing schedule
            # on frames sent into a connection that isn't up yet, so the
            # callee only hears the tail end of the greeting -- cut off /
            # very short, exactly as reported. Wait for the real connected
            # state first (bounded, so a stuck negotiation doesn't hang the
            # request forever).
            connected = await session.wait_until_connected(timeout=10.0)
            if not connected:
                logger.warning(
                    "call %s: peer connection did not reach 'connected' within timeout, "
                    "speaking anyway (may be cut off)",
                    payload.whatsapp_call_id,
                )

            # TtsAudioTrack.recv() blocks on an empty queue, so no RTP packets
            # go out at all until speak() pushes the first chunk -- the
            # callee's client has never seen a single audio packet for this
            # call and its jitter buffer/renderer needs a moment to spin up
            # once packets start arriving. Priming with a short burst of
            # silence gets real RTP flowing before the greeting's actual
            # speech is queued, so the cold-start clipping lands on silence
            # instead of on "welcome to ...".
            session.audio_track.push_pcm(b"\x00" * (SAMPLE_RATE // 2 * 2))
            await asyncio.sleep(0.5)

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
