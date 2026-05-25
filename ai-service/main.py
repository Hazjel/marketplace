import asyncio
import time
from contextlib import asynccontextmanager

import redis.asyncio as aioredis
from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from slowapi import Limiter, _rate_limit_exceeded_handler
from slowapi.errors import RateLimitExceeded
from slowapi.util import get_remote_address

from config import CORS_ALLOWED_ORIGINS, REDIS_URL
from metrics import REQUEST_COUNT, REQUEST_LATENCY
from rag import ProductVectorStore, init_vector_store, rag_refresh_loop
from redis_helper import init_redis
from routes.admin import router as admin_router
from routes.chat import router as chat_router

# ---------------------------------------------------------------------------
# LIFESPAN
# ---------------------------------------------------------------------------
@asynccontextmanager
async def lifespan(application: FastAPI):
    # Redis
    r = aioredis.from_url(REDIS_URL, decode_responses=True)
    try:
        await r.ping()  # type: ignore[misc]  # redis-py 7.x stubs incorrectly typed
        print(f"[Startup] Redis connected: {REDIS_URL}")
    except Exception as e:
        print(f"[Startup] WARNING: Redis tidak bisa dikoneksi: {e}")
    init_redis(r)

    # ChromaDB + RAG index (non-fatal jika gagal)
    vs = ProductVectorStore()
    init_vector_store(vs)
    try:
        count = await vs.build_index()
        print(f"[Startup] RAG index ready: {count} produk")
    except Exception as e:
        print(f"[Startup] WARNING: RAG indexing gagal: {e}. Akan retry di background.")

    # Background task: refresh index setiap N jam
    refresh_task = asyncio.create_task(rag_refresh_loop())

    yield

    refresh_task.cancel()
    await r.aclose()
    print("[Shutdown] Cleanup selesai.")


# ---------------------------------------------------------------------------
# APP FACTORY
# ---------------------------------------------------------------------------
limiter = Limiter(key_func=get_remote_address)

app = FastAPI(
    title="Blukios AI Service",
    description="RAG-powered chatbot API untuk marketplace Blukios",
    version="2.0.0",
    lifespan=lifespan,
)
app.state.limiter = limiter
app.add_exception_handler(RateLimitExceeded, _rate_limit_exceeded_handler)
app.add_middleware(
    CORSMiddleware,
    allow_origins=CORS_ALLOWED_ORIGINS,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Inject limiter ke routers
from routes import chat as chat_module
from routes import admin as admin_module
chat_module.limiter  = limiter
admin_module.limiter = limiter   # type: ignore[attr-defined]

app.include_router(chat_router)
app.include_router(admin_router)


# ---------------------------------------------------------------------------
# METRICS MIDDLEWARE
# ---------------------------------------------------------------------------
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
    return response
