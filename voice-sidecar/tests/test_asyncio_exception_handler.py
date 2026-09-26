import asyncio
import os
from unittest.mock import Mock, patch

os.environ.setdefault("LARAVEL_BASE_URL", "http://backend/api/internal")
os.environ.setdefault("LARAVEL_SHARED_SECRET", "test-secret")

with patch("app.tts.piper_tts.PiperVoice.load"):
    from app.main import _handle_asyncio_exception


def test_suppresses_benign_aioice_retry_race():
    loop = Mock()
    context = {
        "message": "Exception in callback Transaction.__retry()",
        "exception": asyncio.InvalidStateError(),
    }

    _handle_asyncio_exception(loop, context)

    loop.default_exception_handler.assert_not_called()


def test_delegates_other_invalid_state_errors():
    loop = Mock()
    context = {
        "message": "Exception in callback SomeOtherThing()",
        "exception": asyncio.InvalidStateError(),
    }

    _handle_asyncio_exception(loop, context)

    loop.default_exception_handler.assert_called_once_with(context)


def test_delegates_unrelated_exceptions():
    loop = Mock()
    context = {
        "message": "Exception in callback Transaction.__retry()",
        "exception": RuntimeError("boom"),
    }

    _handle_asyncio_exception(loop, context)

    loop.default_exception_handler.assert_called_once_with(context)
