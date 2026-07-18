from fastapi import APIRouter, HTTPException

from config import CBF_TOP_K
from content_based.similarity import score_similar_products
from utils.cache import get_cached_products
from utils.metrics import CBF_EMPTY_RESULTS, CBF_REQUESTS

router = APIRouter()


@router.get("/recommend/product/{product_id}/similar")
async def similar_products(product_id: str, top_k: int = CBF_TOP_K):
    """
    Produk serupa berdasarkan kategori + kemiripan harga + popularitas/rating.
    Content-based -- selalu bisa jalan tanpa perlu histori interaksi user,
    dipakai di halaman detail produk & sebagai fallback cold-start.
    """
    CBF_REQUESTS.inc()

    products = get_cached_products()
    target = next((p for p in products if p.get("id") == product_id), None)
    if target is None:
        raise HTTPException(status_code=404, detail="Produk tidak ditemukan di cache")

    results = score_similar_products(target, products, top_k=top_k)
    if not results:
        CBF_EMPTY_RESULTS.inc()

    return {"product_id": product_id, "count": len(results), "data": results}
