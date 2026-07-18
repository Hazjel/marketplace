from fastapi import APIRouter, HTTPException

from collaborative.svd import is_model_ready, predict_for_user
from config import CBF_TOP_K, CF_TOP_K
from content_based.similarity import score_similar_products, trending_products
from utils.cache import get_cached_products
from utils.metrics import (
    CBF_EMPTY_RESULTS,
    CBF_REQUESTS,
    CF_FALLBACK_TO_CBF,
    CF_REQUESTS,
)

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


@router.get("/recommend/user/{user_id}")
async def personalized_for_user(user_id: str, top_k: int = CF_TOP_K):
    """
    "Direkomendasikan untuk kamu" -- hybrid switching:
    - Model SVD siap & kenal user ini -> collaborative filtering
    - Model belum siap / user baru (belum ada histori) -> fallback trending
    """
    CF_REQUESTS.inc()

    products = get_cached_products()
    if not products:
        return {"user_id": user_id, "source": "empty", "count": 0, "data": []}

    if is_model_ready():
        candidate_ids = [p["id"] for p in products]
        predictions = predict_for_user(user_id, candidate_ids, top_k=top_k)
        if predictions:
            by_id = {p["id"]: p for p in products}
            results = [
                {**by_id[pid], "_score": round(score, 4)}
                for pid, score in predictions
                if pid in by_id
            ]
            return {"user_id": user_id, "source": "collaborative", "count": len(results), "data": results}

    # Model belum siap atau user belum dikenal (belum cukup histori) -> fallback
    CF_FALLBACK_TO_CBF.inc()
    results = trending_products(products, top_k=top_k)
    return {"user_id": user_id, "source": "trending_fallback", "count": len(results), "data": results}
