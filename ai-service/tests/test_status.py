from main import _status_from_flags


def test_status_success():
    assert _status_from_flags(False, False) == "success"


def test_status_quota_exceeded():
    assert _status_from_flags(True, False) == "quota_exceeded"


def test_status_error():
    assert _status_from_flags(False, True) == "error"
