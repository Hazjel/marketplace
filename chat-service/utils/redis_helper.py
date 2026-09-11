import json

import redis.asyncio as aioredis

import config
from config import (
    LLM_CACHE_KEY,
    LLM_CACHE_TTL_SECONDS,
    MAX_HISTORY_MESSAGES,
    SESSION_KEY,
    SESSION_TTL_SECONDS,
    SUMMARY_KEY,
    SUMMARY_TRIGGER_TURNS,
)

# ---------------------------------------------------------------------------
# Singleton Redis connection — diinisialisasi oleh lifespan di main.py
# ---------------------------------------------------------------------------
_redis: aioredis.Redis | None = None


def init_redis(r: aioredis.Redis) -> None:
    """Dipanggil sekali saat startup oleh lifespan."""
    global _redis
    _redis = r


def _get_redis() -> aioredis.Redis:
    if _redis is None:
        raise RuntimeError("Redis belum terkoneksi.")
    return _redis


# ---------------------------------------------------------------------------
# Connection construction (Sprint C2.1B — shared Redis)
#
# `import config` (not `from config import REDIS_HOST, ...`) is deliberate:
# reading `config.REDIS_HOST` etc. at call time, not at module-import time,
# is what lets tests monkeypatch these onto the config module and see it
# reflected here.
# ---------------------------------------------------------------------------
def redis_connection_kwargs() -> dict:
    """kwargs for `redis.asyncio.Redis(...)` — built from components, never
    a `redis://user:pass@host/db` URL, so a credential string never has to
    exist just to construct the client."""
    return {
        "host": config.REDIS_HOST,
        "port": config.REDIS_PORT,
        "username": config.REDIS_USERNAME,
        "password": config.REDIS_PASSWORD,
        "db": config.REDIS_DB,
        "decode_responses": True,
    }


def redis_connection_summary() -> str:
    """Safe-to-log connection description — deliberately excludes username
    and password so a startup log line can never leak the credential."""
    return f"{config.REDIS_HOST}:{config.REDIS_PORT}/{config.REDIS_DB}"


# ---------------------------------------------------------------------------
# Session History
# ---------------------------------------------------------------------------
async def get_session_history(session_id: str) -> list[dict]:
    """History pendek + ringkasan (jika ada) diprepend sebagai pesan system."""
    try:
        r   = _get_redis()
        raw = await r.get(f"{SESSION_KEY}{session_id}")
        history: list[dict] = json.loads(raw) if raw else []
        history = history[-MAX_HISTORY_MESSAGES:] if len(history) > MAX_HISTORY_MESSAGES else history

        summary = await r.get(f"{SUMMARY_KEY}{session_id}")
        if summary:
            history = [{"role": "system", "content": f"Ringkasan obrolan sebelumnya: {summary}"}] + history
        return history
    except Exception as e:
        print(f"[Redis] get error: {e}")
        return []


async def append_session_history(session_id: str, user_msg: str, reply: str) -> None:
    try:
        r   = _get_redis()
        key = f"{SESSION_KEY}{session_id}"
        raw = await r.get(key)
        history: list[dict] = json.loads(raw) if raw else []
        history.append({"role": "user",      "content": user_msg})
        history.append({"role": "assistant", "content": reply})

        # Begitu history tembus batas trigger, ringkas bagian tertua lalu
        # gabung ke ringkasan yang sudah ada — mencegah konteks lama hilang
        # begitu saja saat di-trim ke sliding window
        if len(history) > SUMMARY_TRIGGER_TURNS:
            overflow      = history[:-MAX_HISTORY_MESSAGES] if len(history) > MAX_HISTORY_MESSAGES else []
            history       = history[-MAX_HISTORY_MESSAGES:] if len(history) > MAX_HISTORY_MESSAGES else history
            if overflow:
                await _extend_summary(r, session_id, overflow)

        await r.setex(key, SESSION_TTL_SECONDS, json.dumps(history, ensure_ascii=False))
    except Exception as e:
        print(f"[Redis] append error: {e}")


async def _extend_summary(r: aioredis.Redis, session_id: str, overflow: list[dict]) -> None:
    """Ringkas pesan yang akan dibuang, gabung ke ringkasan sesi yang sudah ada."""
    from llm.ollama import summarize_history  # local import — hindari circular import

    try:
        existing    = await r.get(f"{SUMMARY_KEY}{session_id}")
        new_summary = await summarize_history(overflow, existing)
        if new_summary:
            await r.setex(f"{SUMMARY_KEY}{session_id}", SESSION_TTL_SECONDS, new_summary)
    except Exception as e:
        print(f"[Redis] summary error: {e}")


# ---------------------------------------------------------------------------
# LLM Response Cache
# ---------------------------------------------------------------------------
async def get_llm_cache(cache_key: str) -> str | None:
    """Ambil response LLM dari Redis cache jika ada."""
    try:
        return await _get_redis().get(f"{LLM_CACHE_KEY}{cache_key}")
    except Exception:
        return None


async def set_llm_cache(cache_key: str, response: str) -> None:
    """Simpan response LLM ke Redis cache dengan TTL 5 menit."""
    try:
        await _get_redis().setex(
            f"{LLM_CACHE_KEY}{cache_key}",
            LLM_CACHE_TTL_SECONDS,
            response,
        )
    except Exception as e:
        print(f"[Cache] set error: {e}")
