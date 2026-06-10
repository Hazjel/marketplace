import asyncio
import json
from typing import Any

import httpx

from config import OLLAMA_BASE_URL, OLLAMA_CONTEXT_LENGTH, OLLAMA_MODEL, OLLAMA_MAX_RETRIES, OLLAMA_TIMEOUT_S

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
    "Kamu adalah Ri, asisten Blukios (marketplace gadget & elektronik Indonesia). "
    "Jawab dalam bahasa Indonesia santai, maksimal 3 kalimat, tanpa markdown (###, **, --).\n"
    "Jika ada data produk: sebutkan nama produk dan alasan singkat mengapa cocok. "
    "JANGAN tulis harga, nama toko, atau stok — sudah tampil otomatis sebagai kartu di UI.\n"
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
        "options":  {"temperature": temperature if temperature is not None else 0.7, "num_ctx": OLLAMA_CONTEXT_LENGTH},
    }

    last_error: Exception | None = None
    for attempt in range(OLLAMA_MAX_RETRIES + 1):
        try:
            client = get_client()
            r = await client.post(f"{OLLAMA_BASE_URL}/api/chat", json=payload)
            if r.status_code >= 500:
                raise RuntimeError(f"Ollama server error {r.status_code}: {r.text[:200]}")
            if r.status_code >= 400:
                raise RuntimeError(f"Ollama client error {r.status_code}: {r.text[:200]}")
            content = r.json().get("message", {}).get("content")
            if not content:
                raise RuntimeError("Ollama response missing content")
            return content
        except (httpx.TimeoutException, httpx.NetworkError) as err:
            last_error = err
            if attempt < OLLAMA_MAX_RETRIES:
                print(f"[Ollama] Attempt {attempt + 1} failed ({type(err).__name__}), retrying...")
                await asyncio.sleep(0.5 * (attempt + 1))
        except RuntimeError as err:
            if "server error" in str(err) and attempt < OLLAMA_MAX_RETRIES:
                last_error = err
                print(f"[Ollama] Attempt {attempt + 1} failed: {err}, retrying...")
                await asyncio.sleep(0.5 * (attempt + 1))
            else:
                raise
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
        "options":  {"temperature": temperature, "num_ctx": OLLAMA_CONTEXT_LENGTH},
    }
    client = get_client()
    async with client.stream("POST", f"{OLLAMA_BASE_URL}/api/chat", json=payload) as r:
            if r.status_code >= 400:
                body = await r.aread()
                print(f"[Ollama] Stream error {r.status_code}: {body.decode()[:200]}")
                raise RuntimeError(f"Ollama stream error {r.status_code}: {body.decode()[:200]}")
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
