"""
Content-Based Filtering (CBF) sederhana buat "Produk Serupa".

Skor kemiripan dibentuk dari 3 komponen produk itu sendiri (gak butuh
histori interaksi user -- makanya CBF selalu bisa jalan buat produk/user
baru, dipakai juga sebagai fallback kalau data CF belum cukup):

- Kategori sama: kontribusi terbesar (produk beda kategori jarang relevan)
- Kemiripan harga: skor turun makin jauh selisih harga (dinormalisasi
  logaritmik biar gak didominasi produk mahal)
- Rating & popularitas (total_sold): boost kecil, produk laris/rating
  bagus diprioritaskan di antara kandidat yang sama-sama mirip
"""
import math


def _price_similarity(price_a: float, price_b: float) -> float:
    """1.0 kalau harga identik, makin kecil makin jauh (skala logaritmik)."""
    if price_a <= 0 or price_b <= 0:
        return 0.0
    ratio = min(price_a, price_b) / max(price_a, price_b)
    return ratio


def _popularity_score(product: dict, max_sold: int) -> float:
    if max_sold <= 0:
        return 0.0
    return product.get("total_sold", 0) / max_sold


def score_similar_products(
    target: dict,
    candidates: list[dict],
    top_k: int = 8,
) -> list[dict]:
    """
    Cari produk paling mirip dengan `target` dari `candidates`.
    Dipakai buat "Produk Serupa" di halaman detail produk.
    """
    pool = [c for c in candidates if c.get("id") != target.get("id")]
    if not pool:
        return []

    max_sold = max((c.get("total_sold", 0) for c in pool), default=0) or 1

    scored: list[dict] = []
    for c in pool:
        same_category = 1.0 if c.get("category_id") == target.get("category_id") else 0.0
        if same_category == 0.0:
            # produk beda kategori nyaris gak pernah relevan -- skip biar
            # gak nyampah "produk serupa" yang gak nyambung
            continue

        price_sim = _price_similarity(target.get("price", 0), c.get("price", 0))
        pop_score = _popularity_score(c, max_sold)
        rating_score = min(c.get("rating", 0) / 5.0, 1.0)

        # bobot: kategori dominan, harga menengah, popularitas+rating pemanis
        score = (
            same_category * 0.5
            + price_sim * 0.3
            + pop_score * 0.1
            + rating_score * 0.1
        )
        scored.append({**c, "_score": round(score, 4)})

    scored.sort(key=lambda x: x["_score"], reverse=True)
    return scored[:top_k]


def trending_products(candidates: list[dict], top_k: int = 8) -> list[dict]:
    """
    Produk populer (total_sold + rating) tanpa mempertimbangkan kategori
    apa pun -- fallback terakhir buat guest/user tanpa histori sama sekali
    (gak ada target produk buat dibandingkan seperti CBF biasa).
    """
    if not candidates:
        return []

    max_sold = max((c.get("total_sold", 0) for c in candidates), default=0) or 1

    scored = []
    for c in candidates:
        pop_score    = c.get("total_sold", 0) / max_sold
        rating_score = min(c.get("rating", 0) / 5.0, 1.0)
        score = pop_score * 0.7 + rating_score * 0.3
        scored.append({**c, "_score": round(score, 4)})

    scored.sort(key=lambda x: x["_score"], reverse=True)
    return scored[:top_k]
