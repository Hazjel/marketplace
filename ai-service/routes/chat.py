import json
import uuid

from fastapi import APIRouter, Request
from fastapi.responses import StreamingResponse

from config import RATE_LIMIT_PER_MINUTE
from context import prepare_context
from models import ChatRequest, ChatResponse
from nlp import make_cache_key
from ollama import ollama_chat, ollama_stream
from redis_helper import append_session_history, get_llm_cache, set_llm_cache

router = APIRouter()

# limiter diimport dari main agar tidak terjadi duplikasi instance
# Diset oleh main.py via app.state setelah router di-include
from slowapi import Limiter
from slowapi.util import get_remote_address
limiter = Limiter(key_func=get_remote_address)


# ---------------------------------------------------------------------------
# POST /predict  — Non-streaming
# ---------------------------------------------------------------------------
@router.post("/predict", response_model=ChatResponse)
@limiter.limit(f"{RATE_LIMIT_PER_MINUTE}/minute")
async def predict_response(request: Request, body: ChatRequest) -> ChatResponse:
    session_id = body.session_id or str(uuid.uuid4())
    reply_text = ""
    had_error  = False

    try:
        found_products, messages = await prepare_context(session_id, body.message)

        # LLM Cache — cek Redis sebelum hit Ollama
        cache_key    = make_cache_key(body.message)
        cached_reply = await get_llm_cache(cache_key)
        if cached_reply:
            print(f"[Cache] HIT session={session_id}")
            reply_text = cached_reply
        else:
            reply_text = await ollama_chat(messages, temperature=0.7)
            await set_llm_cache(cache_key, reply_text)

        await append_session_history(session_id, body.message, reply_text)
    except Exception as e:
        reply_text = "Duh, Ri lagi pusing nih karena ada error di server, coba tanya lagi nanti ya~"
        print(f"[Predict] Error: {e}")
        had_error = True
        found_products = []

    # Strip internal field _sim sebelum dikirim ke frontend
    clean_products = [{k: v for k, v in p.items() if k != "_sim"} for p in found_products]

    return ChatResponse(
        reply=reply_text,
        status="error" if had_error else "success",
        session_id=session_id,
        products=clean_products or None,
    )


# ---------------------------------------------------------------------------
# POST /predict/stream  — SSE Streaming
# ---------------------------------------------------------------------------
@router.post("/predict/stream")
@limiter.limit(f"{RATE_LIMIT_PER_MINUTE}/minute")
async def predict_stream(request: Request, body: ChatRequest) -> StreamingResponse:
    session_id = body.session_id or str(uuid.uuid4())

    async def _event_generator():
        full_reply_parts: list[str] = []
        found_products:   list[dict] = []
        had_error = False

        try:
            found_products, messages = await prepare_context(session_id, body.message)

            # LLM Cache — jika cache hit, stream kata per kata tanpa hit Ollama
            cache_key    = make_cache_key(body.message)
            cached_reply = await get_llm_cache(cache_key)
            if cached_reply:
                print(f"[Cache] STREAM HIT session={session_id}")
                words      = cached_reply.split()
                chunk_size = 5
                for i in range(0, len(words), chunk_size):
                    token = " ".join(words[i:i + chunk_size]) + (" " if i + chunk_size < len(words) else "")
                    yield f"data: {json.dumps({'token': token, 'done': False, 'session_id': session_id})}\n\n"
                clean = [{k: v for k, v in p.items() if k != "_sim"} for p in found_products]
                yield f"data: {json.dumps({'token': '', 'done': True, 'session_id': session_id, 'products': clean})}\n\n"
                return

            # Cache miss — stream dari Ollama
            async for token, done in ollama_stream(messages, temperature=0.7):
                if token:
                    full_reply_parts.append(token)

                if done:
                    clean = [
                        {
                            "id":        p.get("id", ""),
                            "slug":      p.get("slug", ""),
                            "name":      p.get("name", ""),
                            "price":     p.get("price", 0),
                            "thumbnail": p.get("thumbnail", ""),
                            "store":     p.get("store", ""),
                            "category":  p.get("category", ""),
                            "condition": p.get("condition", ""),
                            "stock":     p.get("stock", 0),
                        }
                        for p in found_products
                    ]
                    event_data = json.dumps(
                        {"token": "", "done": True, "session_id": session_id, "products": clean},
                        ensure_ascii=False,
                    )
                    # Simpan ke cache setelah stream selesai
                    await set_llm_cache(cache_key, "".join(full_reply_parts))
                    yield f"data: {event_data}\n\n"
                    break
                elif token:
                    event_data = json.dumps(
                        {"token": token, "done": False, "session_id": session_id},
                        ensure_ascii=False,
                    )
                    yield f"data: {event_data}\n\n"

        except Exception as e:
            print(f"[Stream] Error: {e}")
            had_error = True
            yield f"data: {json.dumps({'token': 'Duh, Ri lagi error~', 'done': True, 'session_id': session_id, 'error': True}, ensure_ascii=False)}\n\n"

        finally:
            if full_reply_parts and not had_error:
                await append_session_history(session_id, body.message, "".join(full_reply_parts))

    return StreamingResponse(
        _event_generator(),
        media_type="text/event-stream",
        headers={"X-Accel-Buffering": "no", "Cache-Control": "no-cache"},
    )
