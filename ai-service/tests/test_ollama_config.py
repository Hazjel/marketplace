from main import _candidate_ollama_urls


def test_candidate_ollama_urls_fallback_to_11434():
    assert _candidate_ollama_urls("http://localhost:11435") == [
        "http://localhost:11435",
        "http://localhost:11434",
    ]


def test_candidate_ollama_urls_fallback_to_11435():
    assert _candidate_ollama_urls("http://127.0.0.1:11434") == [
        "http://127.0.0.1:11434",
        "http://127.0.0.1:11435",
    ]


def test_candidate_ollama_urls_no_duplicate_when_other_port():
    assert _candidate_ollama_urls("http://ollama:11434") == ["http://ollama:11434"]
