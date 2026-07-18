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

- `GET /health` — cek status service + jumlah produk ter-cache
- `GET /metrics` — Prometheus metrics
- `GET /recommend/product/{product_id}/similar?top_k=8` — produk serupa (content-based, kategori + harga + popularitas/rating)

## Arsitektur

Cache produk in-memory di-refresh berkala dari Laravel API (`PRODUCT_CACHE_REFRESH_MINUTES`, default 30 menit) — bukan query database langsung, biar service ini gak nambah beban ke MySQL/Mongo utama.

Content-based filtering (`content_based/similarity.py`) selalu bisa jalan tanpa histori interaksi user — dipakai buat "Produk Serupa" di halaman detail produk dan sebagai fallback cold-start (user/produk baru) begitu collaborative filtering ditambahkan di iterasi berikutnya.
