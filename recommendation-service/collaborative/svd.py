"""
Collaborative Filtering pakai SVD (matrix factorization) -- belajar pola
"user mirip suka produk apa" dari data interaksi lintas semua user
(purchase/review/wishlist/view, masing-masing beda bobot).

Model ini di-training ulang berkala (lihat scheduler.py), bukan real-time
per-request -- SVD butuh liat keseluruhan matrix user-item sekaligus buat
factorization, gak bisa incremental murah.

Dynamic hybrid: kalau data terlalu sparse (user/interaksi belum cukup),
model gak dilatih sama sekali -- endpoint rekomendasi otomatis fallback ke
CBF penuh. Ini nyegah SVD ngasih hasil ngaco/overfit pas data masih
sedikit (cold-start problem yang dibahas di riset).
"""
import pickle
from collections import defaultdict
from pathlib import Path

import pandas as pd
from surprise import SVD, Dataset, Reader

from config import CF_MIN_ACTIVE_USERS, CF_MIN_INTERACTIONS, CF_MODEL_PATH

# ---------------------------------------------------------------------------
# Singleton -- model yang lagi aktif dipakai buat serve prediksi
# ---------------------------------------------------------------------------
_model: SVD | None = None
_known_users: set[str] = set()
_known_items: set[str] = set()
_user_seen_items: dict[str, set[str]] = defaultdict(set)


def is_model_ready() -> bool:
    return _model is not None


def _aggregate_interactions(interactions: list[dict]) -> dict[tuple[str, str], float]:
    """
    Satu user bisa punya beberapa jenis interaksi ke produk yang sama
    (view + wishlist + purchase) -- jumlahkan bobotnya, capped di 10 biar
    satu user yang super aktif ke 1 produk gak mendominasi skala rating.
    """
    agg: dict[tuple[str, str], float] = defaultdict(float)
    for i in interactions:
        key = (i["user_id"], i["product_id"])
        agg[key] = min(agg[key] + float(i["weight"]), 10.0)
    return agg


def has_enough_data(interactions: list[dict]) -> bool:
    if len(interactions) < CF_MIN_INTERACTIONS:
        return False

    per_user_count: dict[str, int] = defaultdict(int)
    for i in interactions:
        per_user_count[i["user_id"]] += 1

    active_users = sum(1 for c in per_user_count.values() if c >= 2)
    return active_users >= CF_MIN_ACTIVE_USERS


def train(interactions: list[dict]) -> bool:
    """
    Latih ulang model SVD dari data interaksi terbaru.
    Return False (skip training) kalau data masih terlalu sparse.
    """
    global _model, _known_users, _known_items, _user_seen_items

    if not has_enough_data(interactions):
        print(
            f"[CF] Data belum cukup ({len(interactions)} interaksi) -- "
            "skip training, tetap fallback ke CBF."
        )
        return False

    aggregated = _aggregate_interactions(interactions)
    rows = [(u, p, w) for (u, p), w in aggregated.items()]

    reader = Reader(rating_scale=(0, 10))
    dataset = Dataset.load_from_df(
        pd.DataFrame(rows, columns=["user_id", "product_id", "weight"]),
        reader,
    )
    trainset = dataset.build_full_trainset()

    model = SVD(n_factors=20, n_epochs=20, random_state=42)
    model.fit(trainset)

    _model = model
    _known_users = {u for u, _, _ in rows}
    _known_items = {p for _, p, _ in rows}
    _user_seen_items = defaultdict(set)
    for u, p, _ in rows:
        _user_seen_items[u].add(p)

    _save_model()
    print(f"[CF] Model SVD dilatih ulang: {len(rows)} interaksi agregat, "
          f"{len(_known_users)} user, {len(_known_items)} produk.")
    return True


def predict_for_user(user_id: str, candidate_product_ids: list[str], top_k: int = 8) -> list[tuple[str, float]]:
    """
    Prediksi skor preferensi user ke tiap produk kandidat (yang belum
    pernah dia interaksi), urut dari yang paling direkomendasikan.
    """
    if _model is None or user_id not in _known_users:
        return []

    seen = _user_seen_items.get(user_id, set())
    unseen = [p for p in candidate_product_ids if p not in seen]

    scored = [(p, _model.predict(user_id, p).est) for p in unseen]
    scored.sort(key=lambda x: x[1], reverse=True)
    return scored[:top_k]


def _save_model() -> None:
    try:
        path = Path(CF_MODEL_PATH)
        path.parent.mkdir(parents=True, exist_ok=True)
        with open(path, "wb") as f:
            pickle.dump(
                {
                    "model": _model,
                    "known_users": _known_users,
                    "known_items": _known_items,
                    "user_seen_items": dict(_user_seen_items),
                },
                f,
            )
    except Exception as e:
        print(f"[CF] Gagal simpan model ke disk: {e}")


def load_model_from_disk() -> bool:
    """Dipanggil sekali saat startup -- biar gak perlu retrain dari nol tiap restart."""
    global _model, _known_users, _known_items, _user_seen_items

    path = Path(CF_MODEL_PATH)
    if not path.exists():
        return False

    try:
        with open(path, "rb") as f:
            data = pickle.load(f)
        _model = data["model"]
        _known_users = data["known_users"]
        _known_items = data["known_items"]
        _user_seen_items = defaultdict(set, data["user_seen_items"])
        print(f"[CF] Model dimuat dari disk: {len(_known_users)} user, {len(_known_items)} produk.")
        return True
    except Exception as e:
        print(f"[CF] Gagal muat model dari disk: {e}")
        return False
