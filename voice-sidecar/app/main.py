import asyncio
import logging

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel

from app.laravel_client import LaravelClient
from app.stt import UtteranceCollector
from app.tts.openai_tts import OpenAiTtsProvider
from app.webrtc import SAMPLE_RATE, CallSession

logging.basicConfig(level=logging.INFO)
logging.getLogger("aiortc").setLevel(logging.INFO)
logging.getLogger("aioice").setLevel(logging.INFO)
logger = logging.getLogger("voice_sidecar")

app = FastAPI()
laravel = LaravelClient()
tts = OpenAiTtsProvider()

# STT and TTS are now remote HTTP calls (OpenAI-compatible API), not local
# CPU-bound inference, so they no longer compete with the event loop's
# real-time RTP pacing the way the old local Piper/SenseVoice models did.
# The lock still serializes the *network* calls themselves so a slow
# transcription and a slow synthesis on the same call don't race, but it's
# no longer load-bearing for CPU contention.
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
    language: str | None = None
    callback_base_url: str | None = None
    ai_base_url: str | None = None
    ai_api_key: str | None = None
    stt_model: str | None = None
    tts_model: str | None = None
    tts_voice: str | None = None


class SpeakRequest(BaseModel):
    text: str


async def speak(session: CallSession, text: str, voice_id: str | None) -> None:
    if session.pc.connectionState in ("failed", "closed"):
        logger.warning(
            "call %s: skipping speak(), peer connection state is %s (nothing would be heard)",
            session.whatsapp_call_id, session.pc.connectionState,
        )
        return

    if session.call_ended:
        # aiortc's connectionState can still read "connected" here -- Meta
        # ending the inbound track is what actually indicates the call is
        # over. Without this check, a reply that was queued behind a slow
        # transcription gets synthesized and pushed into a call nobody is
        # on anymore, wasting CPU time other real calls need.
        logger.warning(
            "call %s: skipping speak(), inbound track already ended (call is over)",
            session.whatsapp_call_id,
        )
        return

    if not session.ai_base_url:
        logger.warning(
            "call %s: skipping speak(), no AI Assistant base_url configured for this company",
            session.whatsapp_call_id,
        )
        return

    total_bytes = 0
    async with cpu_lock:
        async for chunk in tts.stream(
            text,
            session.ai_base_url,
            session.ai_api_key,
            session.tts_model,
            # A flow-level tts_voice_id override takes precedence over the
            # company-wide default voice.
            voice_id or session.tts_voice,
        ):
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

    session = CallSession(payload.whatsapp_call_id, on_inbound_frame=on_inbound_frame, language=payload.language)
    session.ai_base_url = payload.ai_base_url
    session.ai_api_key = payload.ai_api_key
    session.tts_model = payload.tts_model or "tts-1"
    session.tts_voice = payload.tts_voice or "alloy"
    sessions[payload.whatsapp_call_id] = session

    async def on_utterance(text: str) -> None:
        await handle_caller_utterance(session, payload.tts_voice_id, text)

    if payload.ai_base_url:
        collector_holder["collector"] = UtteranceCollector(
            on_utterance,
            stt_base_url=payload.ai_base_url,
            stt_api_key=payload.ai_api_key,
            stt_model=payload.stt_model or "whisper-1",
            cpu_lock=cpu_lock,
            language=payload.language,
        )
    else:
        logger.warning(
            "call %s: no AI Assistant base_url configured for this company, caller speech will not be transcribed",
            payload.whatsapp_call_id,
        )
        collector_holder["collector"] = UtteranceCollector(
            lambda text: asyncio.sleep(0),
            stt_base_url="",
            stt_api_key=None,
            stt_model="",
            cpu_lock=cpu_lock,
            language=payload.language,
        )

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
    finally:
        # session.is_speaking starts True (see CallSession.__init__) to gate
        # out inbound audio during ICE/DTLS negotiation and the greeting's
        # priming silence, before speak() has run even once. speak() itself
        # already clears it after a real greeting; this covers the no-
        # greeting case and guarantees it's cleared even if setup raised.
        session.is_speaking = False

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
