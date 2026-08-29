from unittest.mock import patch

from fastapi.testclient import TestClient

from main import app

# Beda dari test_internal_auth.py: memanggil endpoint lewat TestClient
# (rute HTTP asli + dependency injection FastAPI sungguhan), bukan
# memanggil _require_internal_key() langsung -- test_internal_auth.py
# tidak akan menangkap mutation yang menghapus
# `Depends(_require_internal_key)` dari signature endpoint itu sendiri.
#
# TestClient TANPA `with` (context manager) tidak menjalankan lifespan
# startup service ini -- diverifikasi langsung, request yang ditolak di
# layer dependency tidak pernah menyentuh koneksi eksternal apa pun.
client = TestClient(app)


def test_internal_retrain_rejects_missing_key():
    with patch("main.INTERNAL_SERVICE_KEY", "secret123"):
        response = client.post("/internal/retrain")
        assert response.status_code == 403


def test_internal_retrain_rejects_wrong_key():
    with patch("main.INTERNAL_SERVICE_KEY", "secret123"):
        response = client.post("/internal/retrain", headers={"X-Internal-Key": "wrong"})
        assert response.status_code == 403
