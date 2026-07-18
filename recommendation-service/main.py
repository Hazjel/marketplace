import asyncio
import time
from contextlib import asynccontextmanager

from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware

from api.recommend import router as recommend_router
from config import CORS_ALLOWED_ORIGINS
from utils.cache import get_cached_products, product_cache_refresh_loop, refresh_product_cache
from utils.metrics import PRODUCTS_CACHED, REQUEST_COUNT, REQUEST_LATENCY


@asynccontextmanager
async def lifespan(application: FastAPI):
    try:
        count = await refresh_product_cache()
        PRODUCTS_CACHED.set(count)
        print(f"[Startup] Product cache ready: {count} produk")
    except Exception as e:
        print(f"[Startup] WARNING: Product cache gagal dimuat: {e}. Akan retry di background.")

    refresh_task = asyncio.create_task(product_cache_refresh_loop())

    yield

    refresh_task.cancel()
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
    return {"status": "ok", "products_cached": len(get_cached_products())}


@app.get("/metrics")
async def metrics():
    from fastapi import Response
    from prometheus_client import CONTENT_TYPE_LATEST, generate_latest

    return Response(generate_latest(), media_type=CONTENT_TYPE_LATEST)
