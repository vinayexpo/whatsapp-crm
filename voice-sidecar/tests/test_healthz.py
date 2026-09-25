import os

os.environ.setdefault("LARAVEL_BASE_URL", "http://backend/api/internal")
os.environ.setdefault("LARAVEL_SHARED_SECRET", "test-secret")
os.environ.setdefault("OPENAI_API_KEY", "test-key")

from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_healthz():
    response = client.get("/healthz")
    assert response.status_code == 200
    assert response.json() == {"status": "ok"}
