import asyncio

from config import RAG_REFRESH_HOURS
from rag.vectorstore import get_vector_store


# ---------------------------------------------------------------------------
# Background Refresh Loop
# ---------------------------------------------------------------------------
async def rag_refresh_loop() -> None:
    """Background task: re-index produk dari Laravel setiap RAG_REFRESH_HOURS jam."""
    consecutive_failures = 0
    while True:
        await asyncio.sleep(RAG_REFRESH_HOURS * 3600)
        vs = get_vector_store()
        if vs is None:
            continue
        try:
            count = await vs.build_index()
            consecutive_failures = 0
            print(f"[RAG] Refresh: {count} produk.")
        except Exception as e:
            consecutive_failures += 1
            if consecutive_failures >= 3:
                print(f"[RAG] ERROR: Refresh gagal {consecutive_failures}x berturut-turut: {e}")
            else:
                print(f"[RAG] Refresh error ({consecutive_failures}x): {e}")
