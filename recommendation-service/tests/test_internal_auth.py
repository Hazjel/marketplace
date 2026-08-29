from unittest.mock import patch

import pytest
from fastapi import HTTPException

from main import _require_internal_key

# /internal/retrain sebelumnya tidak punya autentikasi sama sekali --
# reachable langsung lewat port 8002 yang masih dipublish ke host (C1
# Docker hardening belum di-deploy). Pola sama dengan
# chat-service/api/store_assistant.py dan chat-service/api/admin.py.


def test_missing_header_rejected():
    with patch("main.INTERNAL_SERVICE_KEY", "secret123"):
        with pytest.raises(HTTPException) as exc:
            _require_internal_key(x_internal_key=None)
        assert exc.value.status_code == 403


def test_wrong_key_rejected():
    with patch("main.INTERNAL_SERVICE_KEY", "secret123"):
        with pytest.raises(HTTPException) as exc:
            _require_internal_key(x_internal_key="wrong-key")
        assert exc.value.status_code == 403


def test_correct_key_accepted():
    with patch("main.INTERNAL_SERVICE_KEY", "secret123"):
        assert _require_internal_key(x_internal_key="secret123") is None


def test_unset_internal_service_key_rejects_everything():
    # Fail closed kalau INTERNAL_SERVICE_KEY belum di-set, bukan fail open.
    with patch("main.INTERNAL_SERVICE_KEY", ""):
        with pytest.raises(HTTPException) as exc:
            _require_internal_key(x_internal_key="")
        assert exc.value.status_code == 403
