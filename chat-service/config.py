import os

from dotenv import load_dotenv

load_dotenv(override=False)

# ---------------------------------------------------------------------------
# OLLAMA
# ---------------------------------------------------------------------------
OLLAMA_BASE_URL      = os.getenv("OLLAMA_BASE_URL", "http://localhost:11434").rstrip("/")
OLLAMA_MODEL         = os.getenv("OLLAMA_MODEL", "qwen3:1.7b")
OLLAMA_TIMEOUT_S     = float(os.getenv("OLLAMA_TIMEOUT_S", "180"))
OLLAMA_MAX_RETRIES   = int(os.getenv("OLLAMA_MAX_RETRIES", "1"))
OLLAMA_CONTEXT_LENGTH = int(os.getenv("OLLAMA_CONTEXT_LENGTH", "2048"))

# ---------------------------------------------------------------------------
# LARAVEL API
# ---------------------------------------------------------------------------
LARAVEL_API_URL = os.getenv("LARAVEL_API_URL", "http://localhost:8000").rstrip("/")

# ---------------------------------------------------------------------------
# INTERNAL SERVICE AUTH — dipakai proteksi endpoint yang cuma boleh dipanggil
# Laravel (bukan publik), sama shared secret dengan InternalServiceAuth di sisi
# Laravel (header X-Internal-Key).
# ---------------------------------------------------------------------------
INTERNAL_SERVICE_KEY = os.getenv("INTERNAL_SERVICE_KEY", "")

# ---------------------------------------------------------------------------
# CORS
# ---------------------------------------------------------------------------
CORS_ALLOWED_ORIGINS: list[str] = [
    o.strip()
    for o in os.getenv(
        "CORS_ALLOWED_ORIGINS",
        "http://localhost:3000,http://localhost:5173,http://localhost:8080",
    ).split(",")
    if o.strip()
]

# ---------------------------------------------------------------------------
# RATE LIMIT & SESSION
# ---------------------------------------------------------------------------
RATE_LIMIT_PER_MINUTE = int(os.getenv("RATE_LIMIT_PER_MINUTE", "20"))
SESSION_TTL_SECONDS   = int(os.getenv("SESSION_TTL_SECONDS", "3600"))

# ---------------------------------------------------------------------------
# REDIS CONNECTION (Sprint C2.1B — shared Redis, see
# docs/c2-1b-shared-redis-cutover.md)
#
# Built from components rather than a credential-bearing REDIS_URL, so a
# password never has to be embedded in a string that could end up logged or
# repr()'d — see utils/redis_helper.redis_connection_kwargs()/
# redis_connection_summary(). USERNAME/PASSWORD default to None (Redis
# treats a None password as "no AUTH"), which keeps a bare local Redis
# working without requiring credentials in dev.
# ---------------------------------------------------------------------------
REDIS_HOST     = os.getenv("REDIS_HOST", "localhost")
REDIS_PORT     = int(os.getenv("REDIS_PORT", "6379"))
REDIS_USERNAME = os.getenv("REDIS_USERNAME") or None
REDIS_PASSWORD = os.getenv("REDIS_PASSWORD") or None
REDIS_DB       = int(os.getenv("REDIS_DB", "0"))
# Key-namespace boundary on shared Redis -- NOT the same thing as REDIS_DB.
# Production sets this to "blukios:" so every key this service writes stays
# inside the ACL-restricted `blukios:*` pattern instead of relying on the
# logical DB number as an isolation boundary.
REDIS_KEY_PREFIX = os.getenv("REDIS_KEY_PREFIX", "")

# ---------------------------------------------------------------------------
# CHROMA / RAG
# ---------------------------------------------------------------------------
CHROMA_DB_PATH           = os.getenv("CHROMA_DB_PATH", "/app/chroma_db")
RAG_SIMILARITY_THRESHOLD = float(os.getenv("RAG_SIMILARITY_THRESHOLD", "0.72"))
RAG_TOP_K                = int(os.getenv("RAG_TOP_K", "5"))
RAG_REFRESH_HOURS        = int(os.getenv("RAG_REFRESH_HOURS", "2"))

# ---------------------------------------------------------------------------
# REDIS KEY PREFIXES & CACHE
# ---------------------------------------------------------------------------
MAX_HISTORY_MESSAGES  = 10    # sliding window — 10 messages = 5 turn
SUMMARY_TRIGGER_TURNS = 8     # ringkas begitu history tembus batas ini (sebelum trim)
LLM_CACHE_TTL_SECONDS = 300   # cache response LLM 5 menit


def _prefixed_key(name: str, prefix: str = REDIS_KEY_PREFIX) -> str:
    """Applies REDIS_KEY_PREFIX exactly once. All Redis key constants below
    are built through this — one place to change if the prefix scheme ever
    changes, instead of prepending it at every call site."""
    return f"{prefix}{name}"


SESSION_KEY   = _prefixed_key("chat:session:")
SUMMARY_KEY   = _prefixed_key("chat:summary:")
LLM_CACHE_KEY = _prefixed_key("chat:llmcache:")
FEEDBACK_KEY  = _prefixed_key("chat:feedback:")
