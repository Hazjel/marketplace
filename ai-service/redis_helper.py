import json

import redis.asyncio as aioredis

from config import (
    SESSION_TTL_SECONDS,
    MAX_HISTORY_MESSAGES,
    SUMMARY_TRIGGER_TURNS,
    LLM_CACHE_TTL_SECONDS,
    SESSION_KEY,
    SUMMARY_KEY,
    LLM_CACHE_KEY,
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
    from ollama import summarize_history  # local import — hindari circular import

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
