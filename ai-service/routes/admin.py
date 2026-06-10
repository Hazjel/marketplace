import asyncio
import json
import time
from datetime import datetime, timezone

from fastapi import APIRouter, Request, Response
from prometheus_client import generate_latest, CONTENT_TYPE_LATEST

import rag as rag_module
from config import FEEDBACK_KEY, SESSION_KEY, RAG_SIMILARITY_THRESHOLD, RAG_TOP_K
from metrics import FEEDBACK_RATING, FEEDBACK_TOTAL
from models import FeedbackRequest
from redis_helper import _get_redis

router = APIRouter()


# ---------------------------------------------------------------------------
# POST /feedback
# ---------------------------------------------------------------------------
@router.post("/feedback", status_code=202)
async def submit_feedback(request: Request, body: FeedbackRequest) -> dict:
    try:
        feedback = {
            "session_id": body.session_id,
            "rating":     body.rating,
            "comment":    body.comment,
            "timestamp":  datetime.now(timezone.utc).isoformat(),
        }
        key = f"{FEEDBACK_KEY}{body.session_id}:{int(time.time())}"
        await _get_redis().setex(key, 30 * 24 * 3600, json.dumps(feedback, ensure_ascii=False))

        FEEDBACK_RATING.observe(body.rating)
        FEEDBACK_TOTAL.inc()

        print(f"[Feedback] session={body.session_id} rating={body.rating}")
    except Exception as e:
        print(f"[Feedback] Error: {e}")
        return {"status": "error", "message": "Gagal menyimpan feedback"}
    return {"status": "received"}


# ---------------------------------------------------------------------------
# POST /admin/reindex  — Trigger manual RAG re-index tanpa restart
# ---------------------------------------------------------------------------
@router.post("/admin/reindex")
async def admin_reindex() -> dict:
    """Trigger re-indexing semua produk dari Laravel API ke ChromaDB."""
    vs = rag_module.get_vector_store()
    if vs is None:
        return {"status": "error", "message": "Vector store belum diinisialisasi"}
    try:
        count = await vs.build_index()
        print(f"[Admin] Manual reindex: {count} produk")
        return {"status": "ok", "indexed": count}
    except Exception as e:
        print(f"[Admin] Reindex error: {e}")
        return {"status": "error", "message": str(e)}


# ---------------------------------------------------------------------------
# DELETE /chat/reset/{session_id}
# ---------------------------------------------------------------------------
@router.delete("/chat/reset/{session_id}")
async def chat_reset(session_id: str) -> dict:
    """Hapus history percakapan untuk session tertentu."""
    try:
        key = f"{SESSION_KEY}{session_id}"
        await _get_redis().delete(key)
        print(f"[Chat] Reset session: {session_id}")
        return {"status": "ok", "session_id": session_id}
    except Exception as e:
        print(f"[Chat] Reset error: {e}")
        return {"status": "error", "message": str(e)}


# ---------------------------------------------------------------------------
# GET /metrics  — Prometheus scrape endpoint
# ---------------------------------------------------------------------------
@router.get("/metrics")
def metrics() -> Response:
    return Response(content=generate_latest(), media_type=CONTENT_TYPE_LATEST)


# ---------------------------------------------------------------------------
# GET /eval  — Statistik evaluasi RAG + feedback
# ---------------------------------------------------------------------------
@router.get("/eval")
async def eval_stats() -> dict:
    try:
        redis_client = _get_redis()

        feedback_keys: list[str] = []
        async for key in redis_client.scan_iter(f"{FEEDBACK_KEY}*"):
            feedback_keys.append(key)

        ratings: list[int] = []
        if feedback_keys:
            raw_values = await redis_client.mget(*feedback_keys[:500])
            for raw in raw_values:
                if raw:
                    fb     = json.loads(raw)
                    rating = fb.get("rating")
                    if isinstance(rating, (int, float)):
                        ratings.append(int(rating))

        distribution = {str(i): 0 for i in range(1, 6)}
        for r in ratings:
            k = str(max(1, min(5, r)))
            distribution[k] += 1

        avg_rating = round(sum(ratings) / len(ratings), 2) if ratings else None
        vs = rag_module.get_vector_store()

        return {
            "rag": {
                "indexed_products":      vs.count() if vs else 0,
                "similarity_threshold":  RAG_SIMILARITY_THRESHOLD,
                "top_k":                 RAG_TOP_K,
            },
            "feedback": {
                "total":        len(ratings),
                "avg_rating":   avg_rating,
                "distribution": distribution,
            },
        }
    except Exception as e:
        return {"error": str(e)}


# ---------------------------------------------------------------------------
# GET /debug/search  — Trace lengkap RAG pipeline
# ---------------------------------------------------------------------------
@router.get("/debug/search")
async def debug_search(q: str, n: int = 10, show_vectors: bool = False) -> dict:
    """Trace RAG pipeline secara transparan, termasuk embedding vector."""
    vs = rag_module.get_vector_store()
    if not vs:
        return {"error": "Vector store belum siap"}

    total_indexed = vs.count()
    if total_indexed == 0:
        return {"error": "Index kosong — belum ada produk yang di-embed"}

    try:
        loop = asyncio.get_event_loop()

        query_vector: list[float] = await loop.run_in_executor(
            None, lambda: [float(v) for v in vs._ef([q])[0]]
        )
        vector_dim = len(query_vector)

        include_fields = ["metadatas", "distances", "documents"]
        if show_vectors:
            include_fields.append("embeddings")

        raw_results = await loop.run_in_executor(
            None,
            lambda: vs._collection.query(
                query_texts=[q],
                n_results=min(n, total_indexed),
                include=include_fields,
            ),
        )

        above: list[dict] = []
        below: list[dict] = []

        metadatas  = raw_results.get("metadatas",  [[]])[0]
        distances  = raw_results.get("distances",  [[]])[0]
        documents  = raw_results.get("documents",  [[]])[0]
        embeddings = raw_results.get("embeddings", [[]])[0] if show_vectors else []

        for rank, (meta, dist, doc) in enumerate(zip(metadatas, distances, documents), start=1):
            similarity = round(max(0.0, min(1.0, 1.0 - dist / 2.0)), 6)
            entry: dict = {
                "rank":            rank,
                "name":            meta.get("name", ""),
                "category":        meta.get("category", ""),
                "price":           meta.get("price", 0),
                "store":           meta.get("store", ""),
                "condition":       meta.get("condition", ""),
                "stock":           meta.get("stock", 0),
                "lolos_threshold": similarity >= RAG_SIMILARITY_THRESHOLD,
                "similarity_score": similarity,
                "cosine_distance":  round(dist, 6),
                "formula":          f"1 - ({round(dist,4)} / 2) = {similarity}",
                "embedded_text":    doc,
            }

            if show_vectors and embeddings:
                prod_vec = embeddings[rank - 1] if rank - 1 < len(embeddings) else []
                entry["product_vector"] = {
                    "dimensions":     len(prod_vec),
                    "preview_first_8": [float(round(v, 6)) for v in prod_vec[:8]],
                    "full_vector":     [float(round(v, 6)) for v in prod_vec],
                }

            if similarity >= RAG_SIMILARITY_THRESHOLD:
                above.append(entry)
            else:
                below.append(entry)

        return {
            "pipeline": {
                "step_1_input":  f"'{q}'",
                "step_2_model":  "all-MiniLM-L6-v2 (384 dimensi)",
                "step_3_store":  "ChromaDB (cosine similarity)",
                "step_4_filter": f"threshold >= {RAG_SIMILARITY_THRESHOLD}",
                "step_5_output": f"{len(above)} produk diinjeksi ke {rag_module.get_vector_store() and 'qwen3:1.7b'}",
            },
            "query_embedding": {
                "text":            q,
                "dimensions":      vector_dim,
                "preview_first_8": [float(round(v, 6)) for v in query_vector[:8]],
                **({"full_vector": [float(round(v, 6)) for v in query_vector]} if show_vectors else {}),
            },
            "search_summary": {
                "total_indexed":   total_indexed,
                "top_k_requested": n,
                "above_threshold": len(above),
                "below_threshold": len(below),
                "threshold":       RAG_SIMILARITY_THRESHOLD,
            },
            "results_above_threshold": above,
            "results_below_threshold": below,
        }

    except Exception as e:
        return {"error": str(e)}


# ---------------------------------------------------------------------------
# GET /health
# ---------------------------------------------------------------------------
@router.get("/health")
async def health() -> dict:
    vs = rag_module.get_vector_store()
    return {
        "status":   "ok",
        "indexed":  vs.count() if vs else 0,
    }
