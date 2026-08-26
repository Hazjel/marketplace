from unittest.mock import patch

from fastapi.testclient import TestClient

from main import app

# Beda dari test_admin_auth.py: tes di sini memanggil endpoint lewat
# TestClient (rute HTTP asli + dependency injection FastAPI sungguhan),
# bukan memanggil _require_internal_key() langsung. test_admin_auth.py
# tidak akan menangkap mutation yang menghapus
# `Depends(_require_internal_key)` dari signature endpoint -- fungsinya
# sendiri tetap benar, cuma tidak lagi terpasang ke rute-nya. Tes ini
# menutup celah itu.
#
# TestClient TANPA `with` (context manager) tidak menjalankan lifespan
# startup (init Redis/Ollama/ChromaDB) -- diverifikasi langsung, request
# yang ditolak di layer dependency (403 sebelum masuk endpoint body)
# tidak pernah menyentuh koneksi itu sama sekali, jadi tidak perlu
# service eksternal apa pun hidup untuk tes ini.
client = TestClient(app)


def test_admin_reindex_rejects_missing_key():
    with patch("api.admin.INTERNAL_SERVICE_KEY", "secret123"):
        response = client.post("/admin/reindex")
        assert response.status_code == 403


def test_admin_reindex_rejects_wrong_key():
    with patch("api.admin.INTERNAL_SERVICE_KEY", "secret123"):
        response = client.post("/admin/reindex", headers={"X-Internal-Key": "wrong"})
        assert response.status_code == 403


def test_eval_rejects_missing_key():
    with patch("api.admin.INTERNAL_SERVICE_KEY", "secret123"):
        response = client.get("/eval")
        assert response.status_code == 403


def test_debug_search_rejects_missing_key():
    with patch("api.admin.INTERNAL_SERVICE_KEY", "secret123"):
        response = client.get("/debug/search", params={"q": "test"})
        assert response.status_code == 403
