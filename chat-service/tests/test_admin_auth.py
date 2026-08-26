from unittest.mock import patch

import pytest
from fastapi import HTTPException

from api.admin import _require_internal_key

# /admin/reindex, /eval, dan /debug/search sebelumnya tidak punya
# autentikasi sama sekali -- siapa pun yang menjangkau service ini (lewat
# nginx /ai/* yang di-proxy penuh, atau langsung ke port container) bisa
# memanggilnya. _require_internal_key sekarang jadi dependency wajib di
# ketiganya, meniru pola X-Internal-Key yang sudah ada di
# api/store_assistant.py.


def test_missing_header_rejected():
    with patch("api.admin.INTERNAL_SERVICE_KEY", "secret123"):
        with pytest.raises(HTTPException) as exc:
            _require_internal_key(x_internal_key=None)
        assert exc.value.status_code == 403


def test_wrong_key_rejected():
    with patch("api.admin.INTERNAL_SERVICE_KEY", "secret123"):
        with pytest.raises(HTTPException) as exc:
            _require_internal_key(x_internal_key="wrong-key")
        assert exc.value.status_code == 403


def test_correct_key_accepted():
    with patch("api.admin.INTERNAL_SERVICE_KEY", "secret123"):
        # Tidak raise -- return None berarti dependency lolos.
        assert _require_internal_key(x_internal_key="secret123") is None


def test_unset_internal_service_key_rejects_everything():
    # Kalau INTERNAL_SERVICE_KEY kosong (belum di-set di .env), jangan
    # sampai perbandingan "" == "" meloloskan request tanpa header sama
    # sekali -- fail closed, bukan fail open.
    with patch("api.admin.INTERNAL_SERVICE_KEY", ""):
        with pytest.raises(HTTPException) as exc:
            _require_internal_key(x_internal_key="")
        assert exc.value.status_code == 403
