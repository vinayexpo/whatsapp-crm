import os
from unittest.mock import patch

os.environ.setdefault("LARAVEL_BASE_URL", "http://backend/api/internal")
os.environ.setdefault("LARAVEL_SHARED_SECRET", "test-secret")

from fastapi.testclient import TestClient

with patch("app.tts.piper_tts.PiperVoice.load"):
    from app.main import app

client = TestClient(app)


def test_healthz():
    response = client.get("/healthz")
    assert response.status_code == 200
    assert response.json() == {"status": "ok"}
