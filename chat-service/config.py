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
REDIS_URL             = os.getenv("REDIS_URL", "redis://localhost:6379/0")

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
SESSION_KEY   = "chat:session:"
SUMMARY_KEY   = "chat:summary:"
LLM_CACHE_KEY = "chat:llmcache:"
FEEDBACK_KEY  = "chat:feedback:"
