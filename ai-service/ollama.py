import asyncio
import json
from typing import Any

import httpx

from config import OLLAMA_BASE_URL, OLLAMA_MODEL, OLLAMA_TIMEOUT_S, OLLAMA_MAX_RETRIES

_client: httpx.AsyncClient | None = None


def get_client() -> httpx.AsyncClient:
    global _client
    if _client is None or _client.is_closed:
        _client = httpx.AsyncClient(timeout=httpx.Timeout(OLLAMA_TIMEOUT_S))
    return _client

# ---------------------------------------------------------------------------
# System Prompt
# ---------------------------------------------------------------------------
SYSTEM_PROMPT = (
    "Kamu adalah Ri, asisten virtual Blukios yang ramah, sopan, dan profesional. "
    "Blukios adalah marketplace multi-vendor yang menjual gadget, elektronik, dan aksesoris berkualitas. "
    "Gunakan bahasa Indonesia yang santai tapi tetap profesional. "
    "Tugasmu adalah:\n"
    "1. Membantu pengguna mencari dan menemukan produk yang mereka butuhkan.\n"
    "2. Menjawab pertanyaan seputar produk, harga, spesifikasi, dan ketersediaan.\n"
    "3. Memberikan rekomendasi singkat berdasarkan kebutuhan pengguna.\n"
    "4. Menjelaskan kebijakan marketplace seperti pembayaran, pengiriman, dan pengembalian.\n"
    "PENTING — Jika ada data produk yang disediakan:\n"
    "- Jangan list semua detail produk (harga, toko, stok) secara lengkap di dalam teks jawabanmu.\n"
    "- Frontend sudah menampilkan kartu produk dengan detail lengkap secara otomatis.\n"
    "- Cukup sebutkan nama produk dan berikan rekomendasi singkat mengapa produk itu cocok.\n"
    "- Jawaban maksimal 3-4 kalimat. Jangan gunakan format markdown (###, **, --).\n"
    "Jika tidak ada produk relevan, jawab pertanyaan umum secara singkat."
)


# ---------------------------------------------------------------------------
# Non-Streaming Chat
# ---------------------------------------------------------------------------
async def ollama_chat(messages: list[dict], temperature: float | None = None) -> str:
    payload: dict[str, Any] = {
        "model":    OLLAMA_MODEL,
        "messages": messages,
        "stream":   False,
        "think":    False,
        "options":  {"temperature": temperature if temperature is not None else 0.7, "num_ctx": 1024},
    }

    last_error: Exception | None = None
    for attempt in range(OLLAMA_MAX_RETRIES + 1):
        try:
            client = get_client()
            r = await client.post(f"{OLLAMA_BASE_URL}/api/chat", json=payload)
            if r.status_code >= 400:
                raise RuntimeError(f"Ollama error {r.status_code}: {r.text[:200]}")
            content = r.json().get("message", {}).get("content")
            if not content:
                raise RuntimeError("Ollama response missing content")
            return content
        except (httpx.TimeoutException, httpx.NetworkError, RuntimeError) as err:
            last_error = err
            if attempt < OLLAMA_MAX_RETRIES:
                await asyncio.sleep(0.5 * (attempt + 1))
    raise last_error or RuntimeError("Unknown Ollama error")


# ---------------------------------------------------------------------------
# Streaming Chat
# ---------------------------------------------------------------------------
async def ollama_stream(messages: list[dict], temperature: float = 0.7):
    payload: dict[str, Any] = {
        "model":    OLLAMA_MODEL,
        "messages": messages,
        "stream":   True,
        "think":    False,
        "options":  {"temperature": temperature, "num_ctx": 1024},
    }
    client = get_client()
    async with client.stream("POST", f"{OLLAMA_BASE_URL}/api/chat", json=payload) as r:
            if r.status_code >= 400:
                body = await r.aread()
                raise RuntimeError(f"Ollama stream error {r.status_code}: {body.decode()}")
            async for line in r.aiter_lines():
                if not line.strip():
                    continue
                try:
                    chunk = json.loads(line)
                except json.JSONDecodeError:
                    continue
                token: str  = chunk.get("message", {}).get("content", "")
                done:  bool = chunk.get("done", False)
                yield token, done
                if done:
                    break
