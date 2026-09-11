import os
from urllib.parse import urlparse

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
def _resolve_redis_config(env: dict) -> dict:
    """Resolves the Redis connection from environment variables.

    REDIS_HOST/PORT/USERNAME/PASSWORD/DB are authoritative whenever
    REDIS_HOST is present. REDIS_URL is parsed only as a **transitional,
    cutover-window-only fallback** for when REDIS_HOST is absent but a
    legacy REDIS_URL is: the production chat-service container runs
    uvicorn --reload against a bind-mounted checkout, and Jenkins updates
    that checkout (git reset) *before* rebuilding/recreating the
    container. That means the still-running old process can reload this
    new source while its container environment still only has the OLD
    REDIS_URL var -- without this fallback it would silently fall through
    to the "localhost" default and lose Redis for the rest of the build
    window, even though the old Redis it names is still up and reachable
    at that point (nothing removes it until deploy finishes).

    Never logs the URL or any parsed credential -- see
    redis_helper.redis_connection_summary(). Safe to delete once every
    environment (dev included) has migrated to the component vars and no
    running container still only sets REDIS_URL.
    """
    host     = env.get("REDIS_HOST")
    port     = env.get("REDIS_PORT")
    username = env.get("REDIS_USERNAME")
    password = env.get("REDIS_PASSWORD")
    db       = env.get("REDIS_DB")

    legacy_url = env.get("REDIS_URL")
    if host is None and legacy_url:
        parsed   = urlparse(legacy_url)
        host     = parsed.hostname
        port     = port or (str(parsed.port) if parsed.port is not None else None)
        username = username or parsed.username
        password = password or parsed.password
        db       = db or (parsed.path.lstrip("/") or None)

    return {
        "host": host or "localhost",
        "port": int(port or "6379"),
        "username": username or None,
        "password": password or None,
        "db": int(db or "0"),
    }


_redis_config  = _resolve_redis_config(os.environ)
REDIS_HOST     = _redis_config["host"]
REDIS_PORT     = _redis_config["port"]
REDIS_USERNAME = _redis_config["username"]
REDIS_PASSWORD = _redis_config["password"]
REDIS_DB       = _redis_config["db"]
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
    """Prepends `prefix` to `name`. Not idempotent in general -- calling it
    on an already-prefixed string would double up -- but every call site
    below passes one of the fixed, never-prefixed "chat:*" literals, so in
    this module each of SESSION_KEY/SUMMARY_KEY/LLM_CACHE_KEY/FEEDBACK_KEY
    ends up carrying exactly one REDIS_KEY_PREFIX. One place to change if
    the prefix scheme ever changes, instead of prepending it at every call
    site that builds a Redis key."""
    return f"{prefix}{name}"


SESSION_KEY   = _prefixed_key("chat:session:")
SUMMARY_KEY   = _prefixed_key("chat:summary:")
LLM_CACHE_KEY = _prefixed_key("chat:llmcache:")
FEEDBACK_KEY  = _prefixed_key("chat:feedback:")
