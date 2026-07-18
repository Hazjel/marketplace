import asyncio
import time
from contextlib import asynccontextmanager

from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware

from api.recommend import router as recommend_router
from collaborative.scheduler import cf_retrain_loop, retrain_once
from collaborative.svd import load_model_from_disk
from config import CORS_ALLOWED_ORIGINS
from utils.cache import get_cached_products, product_cache_refresh_loop, refresh_product_cache
from utils.metrics import CF_MODEL_TRAINED, PRODUCTS_CACHED, REQUEST_COUNT, REQUEST_LATENCY


@asynccontextmanager
async def lifespan(application: FastAPI):
    try:
        count = await refresh_product_cache()
        PRODUCTS_CACHED.set(count)
        print(f"[Startup] Product cache ready: {count} produk")
    except Exception as e:
        print(f"[Startup] WARNING: Product cache gagal dimuat: {e}. Akan retry di background.")

    # model lama dari disk dulu (kalau ada) biar gak nunggu training pertama
    # kali selesai buat mulai serve rekomendasi personalisasi
    loaded = load_model_from_disk()
    CF_MODEL_TRAINED.set(1 if loaded else 0)
    if not loaded:
        try:
            trained = await retrain_once()
            print(f"[Startup] CF training awal: {'berhasil' if trained else 'data belum cukup, fallback CBF'}")
        except Exception as e:
            print(f"[Startup] WARNING: CF training awal gagal: {e}")

    refresh_task = asyncio.create_task(product_cache_refresh_loop())
    retrain_task  = asyncio.create_task(cf_retrain_loop())

    yield

    refresh_task.cancel()
    retrain_task.cancel()
    print("[Shutdown] Cleanup selesai.")


app = FastAPI(
    title="Blukios Recommendation Service",
    description="Content-based & collaborative filtering buat rekomendasi produk",
    version="1.0.0",
    lifespan=lifespan,
)
app.add_middleware(
    CORSMiddleware,
    allow_origins=CORS_ALLOWED_ORIGINS,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.include_router(recommend_router)


@app.middleware("http")
async def metrics_middleware(request: Request, call_next):
    start    = time.perf_counter()
    response = await call_next(request)
    REQUEST_LATENCY.labels(endpoint=request.url.path).observe(time.perf_counter() - start)
    REQUEST_COUNT.labels(
        method=request.method,
        endpoint=request.url.path,
        status=str(response.status_code),
    ).inc()
    PRODUCTS_CACHED.set(len(get_cached_products()))
    return response


@app.get("/health")
async def health():
    from collaborative.svd import is_model_ready

    return {
        "status": "ok",
        "products_cached": len(get_cached_products()),
        "cf_model_ready": is_model_ready(),
    }


@app.post("/internal/retrain")
async def trigger_retrain():
    """Trigger retrain manual (testing/debug) -- bukan dipanggil rutin, ada scheduler-nya sendiri."""
    trained = await retrain_once()
    return {"trained": trained}


@app.get("/metrics")
async def metrics():
    from fastapi import Response
    from prometheus_client import CONTENT_TYPE_LATEST, generate_latest

    return Response(generate_latest(), media_type=CONTENT_TYPE_LATEST)
