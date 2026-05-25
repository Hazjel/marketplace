import asyncio

import rag as rag_module
from config import RAG_TOP_K
from metrics import RAG_SEARCH_HITS, RAG_SEARCH_MISSES
from nlp import is_general_query, rewrite_query, extract_metadata_filters
from ollama import SYSTEM_PROMPT
from redis_helper import get_session_history


# ---------------------------------------------------------------------------
# Context String Builder
# ---------------------------------------------------------------------------
def build_product_context(products: list[dict]) -> str:
    """Bangun context string dari hasil RAG search untuk diinjeksi ke LLM."""
    if not products:
        return ""   # Pertanyaan umum — jangan inject apapun

    lines = "\n".join([
        f"- {p['name']} | Kategori: {p.get('category','')} | Kondisi: {p.get('condition','')}"
        for p in products
    ])
    return (
        f"\n[SISTEM: Produk yang tersedia di Blukios sesuai pertanyaan user "
        f"(detail harga & toko sudah ditampilkan sebagai kartu di UI):\n"
        f"{lines}\n"
        f"Berikan rekomendasi SINGKAT — sebutkan nama produk dan alasan singkat mengapa cocok. "
        f"Jangan ulangi harga atau detail toko karena sudah ada di kartu produk.]"
    )


# ---------------------------------------------------------------------------
# Prepare Context (Hybrid RAG + Session History)
# ---------------------------------------------------------------------------
async def prepare_context(session_id: str, user_msg: str) -> tuple[list[dict], list[dict]]:
    """
    Hybrid RAG + session history — dijalankan PARALEL via asyncio.gather.
    1. Intent detection: skip RAG jika query adalah sapaan/chat umum
    2. Query rewriting: bersihkan noise sebelum embedding
    3. Metadata filter: ekstrak price/condition/category dari natural language
    4. Semantic search: temukan produk berdasarkan makna (vector similarity)
    5. Keyword search: temukan produk berdasarkan nama/merek (substring match)
    6. Merge kedua hasil — keyword hits diprioritaskan
    7. Ambil history percakapan (Redis)
    """
    async def _empty() -> list[dict]:
        return []

    vs = rag_module.get_vector_store()
    is_general = is_general_query(user_msg)

    if vs and not is_general:
        rewritten   = rewrite_query(user_msg)
        meta_filter = extract_metadata_filters(user_msg)

        if rewritten != user_msg:
            print(f"[RAG] Rewrite: '{user_msg[:40]}' → '{rewritten}'")
        if meta_filter:
            print(f"[RAG] Metadata filter: {meta_filter}")

        semantic_task = vs.search(rewritten, where=meta_filter)
        keyword_task  = vs.keyword_search(user_msg)   # keyword pakai query asli
    else:
        semantic_task = _empty()
        keyword_task  = _empty()
        if is_general:
            print(f"[RAG] Skipping product search — detected general query: '{user_msg[:40]}'")

    semantic_results, keyword_results, history = await asyncio.gather(
        semantic_task, keyword_task, get_session_history(session_id)
    )

    # Merge: keyword hits lebih reliable untuk name queries
    seen_ids: set[str] = set()
    products: list[dict] = []

    for p in keyword_results:
        pid = p.get("id", "")
        if pid not in seen_ids:
            seen_ids.add(pid)
            products.append(p)

    for p in semantic_results:
        pid = p.get("id", "")
        if pid not in seen_ids:
            seen_ids.add(pid)
            products.append(p)

    products = products[:RAG_TOP_K]

    context_info       = build_product_context(products)
    final_user_content = f"{user_msg} {context_info}".strip()

    messages = [
        {"role": "system", "content": SYSTEM_PROMPT},
        *history,
        {"role": "user", "content": final_user_content},
    ]

    sim_scores = [p.get("_sim", 0) for p in products]
    kw_count   = len(keyword_results)
    sem_count  = len(semantic_results)
    print(f"[RAG] session={session_id} keyword={kw_count} semantic={sem_count} merged={len(products)} sim={sim_scores}")

    # Prometheus
    if products:
        RAG_SEARCH_HITS.inc()
    else:
        RAG_SEARCH_MISSES.inc()

    return products, messages
