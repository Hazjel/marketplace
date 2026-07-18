# Recommendation Service

FastAPI service buat rekomendasi produk Blukios — hybrid content-based + collaborative filtering.

## Setup lokal

```bash
cd recommendation-service
python -m venv venv
.\venv\Scripts\activate
pip install -r requirements.txt
cp .env.example .env
uvicorn main:app --reload --port 8002
```

## Endpoint

- `GET /health` — cek status service + jumlah produk ter-cache + status model CF
- `GET /metrics` — Prometheus metrics
- `GET /recommend/product/{product_id}/similar?top_k=8` — produk serupa (content-based, kategori + harga + popularitas/rating)
- `GET /recommend/user/{user_id}?top_k=8` — rekomendasi personalisasi (hybrid: collaborative filtering kalau model siap & kenal user, fallback trending kalau belum)
- `POST /internal/retrain` — trigger retrain model SVD manual (testing/debug, ada scheduler otomatis juga)

## Arsitektur

Cache produk in-memory di-refresh berkala dari Laravel API (`PRODUCT_CACHE_REFRESH_MINUTES`, default 30 menit) — bukan query database langsung, biar service ini gak nambah beban ke MySQL/Mongo utama.

**Content-based filtering** (`content_based/similarity.py`) selalu bisa jalan tanpa histori interaksi user — dipakai buat "Produk Serupa" di halaman detail produk dan sebagai fallback trending (produk populer) kalau collaborative filtering belum siap.

**Collaborative filtering** (`collaborative/svd.py`) pakai matrix factorization SVD (`scikit-surprise`), dilatih dari data interaksi (`purchase` bobot 5, `wishlist` bobot 3, `review` bobot = rating 1-5, `view` bobot 1) yang diambil dari endpoint `GET /api/internal/interactions` di Laravel (proteksi shared secret `INTERNAL_SERVICE_KEY`, bukan auth token user).

Dynamic hybrid switching — model SVD **gak dilatih sama sekali** kalau data masih terlalu sparse (`CF_MIN_INTERACTIONS`, `CF_MIN_ACTIVE_USERS`), biar gak ngasih hasil ngaco pas data sedikit (cold-start). Endpoint `/recommend/user/{id}` otomatis fallback ke trending produk kalau model belum siap atau user belum dikenal model.

Retraining otomatis tiap `CF_RETRAIN_INTERVAL_HOURS` jam (default 24) lewat `collaborative/scheduler.py`. Model tersimpan di `models/svd_model.pkl` (volume Docker `reco_models`, persist antar restart).
