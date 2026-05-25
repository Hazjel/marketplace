import os
import re
import asyncio
import json
import time
import uuid
from contextlib import asynccontextmanager
from datetime import datetime, timezone
from typing import Any

import chromadb
from chromadb.utils.embedding_functions import DefaultEmbeddingFunction
import httpx
import redis.asyncio as aioredis
from fastapi import FastAPI, Request, Response
from fastapi.responses import StreamingResponse
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field
from dotenv import load_dotenv
from prometheus_client import Counter, Gauge, Histogram, generate_latest, CONTENT_TYPE_LATEST
from slowapi import Limiter, _rate_limit_exceeded_handler
from slowapi.errors import RateLimitExceeded
from slowapi.util import get_remote_address

load_dotenv(override=False)

# ---------------------------------------------------------------------------
# KONFIGURASI
# ---------------------------------------------------------------------------
OLLAMA_BASE_URL    = os.getenv("OLLAMA_BASE_URL", "http://localhost:11434").rstrip("/")
OLLAMA_MODEL       = os.getenv("OLLAMA_MODEL", "qwen3:1.7b")
OLLAMA_TIMEOUT_S   = float(os.getenv("OLLAMA_TIMEOUT_S", "180"))
OLLAMA_MAX_RETRIES = int(os.getenv("OLLAMA_MAX_RETRIES", "1"))

LARAVEL_API_URL = os.getenv("LARAVEL_API_URL", "http://localhost:8000").rstrip("/")

CORS_ALLOWED_ORIGINS = [
    o.strip()
    for o in os.getenv(
        "CORS_ALLOWED_ORIGINS",
        "http://localhost:3000,http://localhost:5173,http://localhost:8080",
    ).split(",")
    if o.strip()
]

RATE_LIMIT_PER_MINUTE = int(os.getenv("RATE_LIMIT_PER_MINUTE", "20"))
SESSION_TTL_SECONDS   = int(os.getenv("SESSION_TTL_MINUTES", "60")) * 60
REDIS_URL             = os.getenv("REDIS_URL", "redis://localhost:6379/0")

CHROMA_DB_PATH          = os.getenv("CHROMA_DB_PATH", "/app/chroma_db")
RAG_SIMILARITY_THRESHOLD = float(os.getenv("RAG_SIMILARITY_THRESHOLD", "0.55"))
RAG_TOP_K               = int(os.getenv("RAG_TOP_K", "5"))
RAG_REFRESH_HOURS       = int(os.getenv("RAG_REFRESH_HOURS", "2"))

MAX_HISTORY_MESSAGES  = 20          # sliding window — 20 messages = 10 turn
LLM_CACHE_TTL_SECONDS = 300         # cache response LLM selama 5 menit
_SESSION_KEY  = "chat:session:"
_LLM_CACHE_KEY = "chat:llmcache:"
_FEEDBACK_KEY = "chat:feedback:"

# Layer 1: Sapaan/closing eksplisit — match exact, langsung skip RAG
_GREETING_PATTERN = re.compile(
    r"^(halo|hai|hi|hey|hello|selamat|pagi|siang|malam|sore|apa kabar|makasih|terima kasih"
    r"|thanks|thank you|oke|ok|baik|siap|mantap|keren|lanjut|done|selesai|noted|sip)[\s!?.]*$",
    re.IGNORECASE,
)

# Layer 2: Token-based product intent scoring
# Jika query mengandung >= 1 kata ini, PASTI butuh RAG
_PRODUCT_INTENT_TOKENS = {
    # Aksi belanja
    "beli", "pesan", "order", "bayar", "checkout", "keranjang", "cart",
    # Atribut produk
    "harga", "price", "diskon", "promo", "murah", "mahal", "stok", "stock",
    "spesifikasi", "spek", "spec", "garansi", "warranty",
    # Query produk
    "produk", "barang", "item", "cari", "rekomendasi", "saran", "pilih",
    "laptop", "hp", "handphone", "smartphone", "tablet", "headset", "earphone",
    "mouse", "keyboard", "monitor", "kamera", "camera", "charger", "kabel",
    "gaming", "wireless", "bluetooth", "ram", "ssd", "processor", "gpu",
    # Toko & pengiriman
    "toko", "seller", "pengiriman", "ongkir", "kirim", "ekspedisi",
    # Kondisi
    "baru", "bekas", "second", "refurbished",
    # Brand umum (lowercase)
    "asus", "samsung", "apple", "xiaomi", "oppo", "vivo", "realme",
    "logitech", "sony", "jbl", "anker", "baseus", "razer", "acer", "lenovo",
    "lg", "huawei", "nokia", "google", "dell", "hp", "msi", "corsair",
}

# ---------------------------------------------------------------------------
# REDIS
# ---------------------------------------------------------------------------
_redis: aioredis.Redis | None = None


def _get_redis() -> aioredis.Redis:
    if _redis is None:
        raise RuntimeError("Redis belum terkoneksi.")
    return _redis


async def get_session_history(session_id: str) -> list[dict]:
    try:
        raw = await _get_redis().get(f"{_SESSION_KEY}{session_id}")
        history: list[dict] = json.loads(raw) if raw else []
        # Sliding window — jaga maksimum MAX_HISTORY_MESSAGES pesan
        return history[-MAX_HISTORY_MESSAGES:] if len(history) > MAX_HISTORY_MESSAGES else history
    except Exception as e:
        print(f"[Redis] get error: {e}")
        return []


async def append_session_history(session_id: str, user_msg: str, reply: str) -> None:
    try:
        r   = _get_redis()
        key = f"{_SESSION_KEY}{session_id}"
        raw = await r.get(key)
        history: list[dict] = json.loads(raw) if raw else []
        history.append({"role": "user",      "content": user_msg})
        history.append({"role": "assistant", "content": reply})
        # Trim ke sliding window sebelum simpan
        if len(history) > MAX_HISTORY_MESSAGES:
            history = history[-MAX_HISTORY_MESSAGES:]
        await r.setex(key, SESSION_TTL_SECONDS, json.dumps(history, ensure_ascii=False))
    except Exception as e:
        print(f"[Redis] append error: {e}")


async def get_llm_cache(cache_key: str) -> str | None:
    """Ambil response LLM dari Redis cache jika ada."""
    try:
        return await _get_redis().get(f"{_LLM_CACHE_KEY}{cache_key}")
    except Exception:
        return None


async def set_llm_cache(cache_key: str, response: str) -> None:
    """Simpan response LLM ke Redis cache dengan TTL 5 menit."""
    try:
        await _get_redis().setex(
            f"{_LLM_CACHE_KEY}{cache_key}",
            LLM_CACHE_TTL_SECONDS,
            response,
        )
    except Exception as e:
        print(f"[Cache] set error: {e}")


def _is_general_query(msg: str) -> bool:
    """
    Dua lapis intent detection:
    1. Regex layer: tangkap sapaan/closing eksplisit secara exact
    2. Token scoring: jika tidak ada satu pun product-intent token → skip RAG

    Contoh skip RAG:
      'halo!'              → layer 1 match
      'apakah blukios aman?' → layer 2: tidak ada token produk
      'bagaimana cara pembayaran?' → layer 2: tidak ada token produk

    Contoh TIDAK skip (tetap jalankan RAG):
      'cari laptop gaming'  → layer 2: ada 'cari', 'laptop', 'gaming'
      'harga ASUS ROG berapa?' → layer 2: ada 'harga', 'asus'
    """
    msg_clean = msg.strip()

    # Layer 1: sapaan eksplisit (paling cepat)
    if _GREETING_PATTERN.match(msg_clean):
        return True

    # Layer 2: tidak ada product-intent token sama sekali → general query
    tokens = set(re.sub(r"[^\w\s]", "", msg_clean.lower()).split())
    has_product_intent = bool(tokens & _PRODUCT_INTENT_TOKENS)
    return not has_product_intent


def _rewrite_query(query: str) -> str:
    """
    Query rewriting: hapus kata-kata framing/noise sebelum masuk ke embedding.
    Meningkatkan kualitas cosine similarity karena model fokus ke kata kunci produk.

    Contoh:
      'apakah ada produk bernama ROG?' → 'ROG'
      'cari laptop gaming murah'       → 'laptop gaming murah'
      'rekomendasikan headset wireless' → 'headset wireless'
    """
    noise = {
        "apakah", "adakah", "ada", "tolong", "boleh", "bisa", "minta",
        "carikan", "coba", "bantu", "tampilkan", "lihat", "lihatkan",
        "produk", "barang", "item", "bernama", "namanya", "nama",
        "yang", "dengan", "untuk", "dari", "ke", "di", "dan", "atau",
        "apa", "gimana", "bagaimana", "berapa", "dimana", "apakah",
        "saya", "aku", "gue", "kamu", "kalian",
        "ingin", "mau", "butuh", "perlu", "pengen",
        "rekomendasi", "rekomendasikan", "saran", "sarankan",
        "dong", "deh", "ya", "yuk", "nih", "lah", "sih",
    }
    tokens = [
        t for t in re.sub(r"[^\w\s]", "", query.lower()).split()
        if t not in noise and len(t) >= 2
    ]
    return " ".join(tokens) if tokens else query


# Mapping natural language → nama kategori di database
_CATEGORY_MAP: dict[str, str] = {
    "laptop": "Laptop",     "notebook": "Laptop",     "komputer": "Laptop",
    "smartphone": "Smartphone", "handphone": "Smartphone", "ponsel": "Smartphone",
    "headset": "Aksesoris gadget", "earphone": "Aksesoris gadget", "tws": "Aksesoris gadget",
    "mouse": "Aksesoris gadget", "keyboard": "Aksesoris gadget",
    "monitor": "Aksesoris gadget", "aksesoris": "Aksesoris gadget",
    "smartwatch": "Aksesoris gadget", "wearable": "Aksesoris gadget",
    "elektronik": "Elektronik",  "electronic": "Elektronik",
    "skincare": "Skincare",      "perawatan": "Skincare",
}


def _extract_metadata_filters(query: str) -> dict | None:
    """
    Ekstrak semua filter metadata dari natural language → ChromaDB where clause.
    Menggabungkan: price + condition + category dalam satu $and clause.

    'laptop bekas di bawah 5 juta'
      → {"$and": [{"price": {"$lte": 5000000}}, {"condition": {"$eq": "second"}}, {"category": {"$eq": "Laptop"}}]}
    """
    q       = query.lower()
    clauses: list[dict] = []

    def _to_rupiah(value: str, unit: str) -> int:
        v = float(value.replace(",", "."))
        if "juta" in unit:    return int(v * 1_000_000)
        if any(u in unit for u in ("ribu", "rb", "k")): return int(v * 1_000)
        return int(v)

    # Price — bawah/kurang
    m = re.search(r"(?:di bawah|kurang dari|max|maksimal|under)\s+(\d+(?:[.,]\d+)?)\s*(juta|ribu|rb|k)?", q)
    if m: clauses.append({"price": {"$lte": _to_rupiah(m.group(1), m.group(2) or "juta")}})

    # Price — atas/lebih
    m = re.search(r"(?:di atas|lebih dari|min|minimal|above|over)\s+(\d+(?:[.,]\d+)?)\s*(juta|ribu|rb|k)?", q)
    if m: clauses.append({"price": {"$gte": _to_rupiah(m.group(1), m.group(2) or "juta")}})

    # Price — range X-Y
    m = re.search(r"(\d+(?:[.,]\d+)?)\s*(?:-|sampai|hingga|s/d)\s*(\d+(?:[.,]\d+)?)\s*(juta|ribu|rb|k)?", q)
    if m:
        unit = m.group(3) or "juta"
        clauses.append({"price": {"$gte": _to_rupiah(m.group(1), unit)}})
        clauses.append({"price": {"$lte": _to_rupiah(m.group(2), unit)}})

    # Price — sekitar (±20%)
    m = re.search(r"(?:sekitar|around|kira-kira|kisaran)\s+(\d+(?:[.,]\d+)?)\s*(juta|ribu|rb|k)?", q)
    if m:
        mid = _to_rupiah(m.group(1), m.group(2) or "juta")
        clauses.append({"price": {"$gte": int(mid * 0.8)}})
        clauses.append({"price": {"$lte": int(mid * 1.2)}})

    # Condition filter
    if re.search(r"\b(bekas|second|seken|eks|refurbished)\b", q):
        clauses.append({"condition": {"$eq": "second"}})
    elif re.search(r"\b(baru|new|brand new)\b", q):
        clauses.append({"condition": {"$eq": "new"}})

    # Category filter
    for keyword, category in _CATEGORY_MAP.items():
        if keyword in q:
            clauses.append({"category": {"$eq": category}})
            break

    if not clauses:    return None
    if len(clauses) == 1: return clauses[0]
    return {"$and": clauses}



def _make_cache_key(msg: str, session_id: str) -> str:
    """Buat cache key unik berdasarkan pesan + session (untuk isolasi per user)."""
    import hashlib
    payload = f"{session_id}:{msg.lower().strip()}"
    return hashlib.sha256(payload.encode()).hexdigest()[:24]


# ---------------------------------------------------------------------------
# RAG — ProductVectorStore (ChromaDB + Ollama Embeddings)
# ---------------------------------------------------------------------------
class ProductVectorStore:
    """Menyimpan dan mencari produk menggunakan vector similarity."""

    def __init__(self) -> None:
        # all-MiniLM-L6-v2 via onnxruntime — tidak perlu model Ollama terpisah
        # Model ~92MB didownload dari HuggingFace pada first run, lalu di-cache di chroma_data
        self._ef         = DefaultEmbeddingFunction()
        self._client     = chromadb.PersistentClient(path=CHROMA_DB_PATH)
        self._collection = self._client.get_or_create_collection(
            name="products",
            embedding_function=self._ef,
            metadata={"hnsw:space": "cosine"},
        )

    # Brand keywords yang perlu di-boost dalam embedding
    _KNOWN_BRANDS: frozenset[str] = frozenset({
        "asus", "samsung", "apple", "xiaomi", "oppo", "vivo", "realme",
        "logitech", "sony", "jbl", "anker", "baseus", "razer", "acer",
        "lenovo", "lg", "huawei", "nokia", "dell", "msi", "corsair",
        "keychron", "rexus", "sennheiser", "bose", "nintendo", "cosrx",
    })

    @staticmethod
    def _product_to_text(p: dict) -> str:
        """
        Bangun teks representasi produk untuk embedding.
        Brand name di-boost dengan pengulangan agar similarity lebih kuat.
        """
        name     = p.get("name", "")
        category = p.get("category", "")
        desc     = str(p.get("description", ""))[:300]
        condition = p.get("condition", "")

        # Ekstrak brand dari kata pertama nama produk (jika dikenal atau huruf besar)
        first_word = name.split()[0] if name.split() else ""
        is_brand   = (
            first_word.lower() in ProductVectorStore._KNOWN_BRANDS
            or (len(first_word) >= 2 and first_word.isupper())
        )
        # Brand boost: ulangi nama lengkap sekali lagi agar bobot embedding lebih tinggi
        brand_boost = name if is_brand else ""

        parts = [name, brand_boost, category, desc, f"kondisi {condition}"]
        return " ".join(part for part in parts if part and part.strip())

    async def build_index(self) -> int:
        """Fetch semua produk dari Laravel, embed, simpan di ChromaDB."""
        products = await _fetch_all_products()
        if not products:
            print("[RAG] Tidak ada produk untuk di-index.")
            return 0

        print(f"[RAG] Embedding {len(products)} produk via onnxruntime...")
        texts     = [self._product_to_text(p) for p in products]
        ids       = [str(p.get("id", i)) for i, p in enumerate(products)]
        metadatas = [
            {
                "id":          p.get("id", ""),          # untuk link ke halaman produk
                "slug":        p.get("slug", ""),        # untuk URL /product/:slug
                "name":        p.get("name", ""),
                "price":       float(p.get("price", 0)),
                "thumbnail":   p.get("thumbnail", ""),
                "store":       p.get("store", ""),
                "category":    p.get("category", ""),
                "condition":   p.get("condition", ""),
                "stock":       int(p.get("stock", 0)),
                "total_sold":  int(p.get("total_sold", 0)),
                "description": str(p.get("description", ""))[:500],
            }
            for p in products
        ]

        # ChromaDB embedding + upsert harus sync → thread pool
        loop = asyncio.get_event_loop()
        await loop.run_in_executor(
            None,
            lambda: self._collection.upsert(
                ids=ids,
                documents=texts,       # ChromaDB auto-embed via DefaultEmbeddingFunction
                metadatas=metadatas,
            ),
        )

        total = self._collection.count()
        RAG_INDEXED.set(total)   # Update Prometheus gauge
        print(f"[RAG] Index selesai: {total} produk tersimpan.")
        return total

    async def search(
        self,
        query:     str,
        n_results: int        = RAG_TOP_K,
        threshold: float      = RAG_SIMILARITY_THRESHOLD,
        where:     dict | None = None,   # price/metadata filter (ChromaDB where clause)
    ) -> list[dict]:
        """Cari produk yang maknanya paling mirip dengan query (semantic search)."""
        count = self._collection.count()
        if count == 0:
            return []

        loop = asyncio.get_event_loop()
        try:
            results = await loop.run_in_executor(
                None,
                lambda: self._collection.query(
                    query_texts=[query],
                    n_results=min(n_results, count),
                    where=where or None,   # None = no filter
                ),
            )
        except Exception as e:
            # where filter bisa fail jika tidak ada produk yang match — fallback ke tanpa filter
            print(f"[RAG] search with where failed ({e}), retrying without filter")
            results = await loop.run_in_executor(
                None,
                lambda: self._collection.query(
                    query_texts=[query],
                    n_results=min(n_results, count),
                ),
            )

        products: list[dict] = []
        if results["metadatas"] and results["distances"]:
            for meta, dist in zip(results["metadatas"][0], results["distances"][0]):
                similarity = max(0.0, 1.0 - dist / 2.0)
                if similarity >= threshold:
                    products.append({**meta, "_sim": round(similarity, 3)})

        # Popularity boosting: re-rank dengan weighted score
        # 75% similarity + 25% popularitas (normalized total_sold)
        if products:
            max_sold = max(int(p.get("total_sold", 0)) for p in products) or 1
            for p in products:
                pop_score   = int(p.get("total_sold", 0)) / max_sold
                p["_score"] = round(p["_sim"] * 0.75 + pop_score * 0.25, 4)
            products.sort(key=lambda x: x["_score"], reverse=True)

        return products

    async def keyword_search(self, query: str, n_results: int = RAG_TOP_K) -> list[dict]:
        """
        Keyword search berdasarkan substring dalam nama/deskripsi produk.
        Digunakan sebagai fallback untuk query seperti 'ada produk bernama ROG?'
        """
        count = self._collection.count()
        if count == 0:
            return []

        # Stopwords bahasa Indonesia + kata umum non-produk
        stopwords = {
            "ada", "yang", "apa", "apakah", "dengan", "untuk", "atau",
            "dan", "di", "ke", "dari", "adalah", "ini", "itu", "saya",
            "produk", "bernama", "nama", "beli", "mau", "cari", "tolong",
            "boleh", "bisa", "minta", "butuh", "perlu", "ingin", "want",
            "please", "show", "find", "search", "get", "list",
        }
        # Strip tanda baca, ambil token >= 3 karakter, bukan stopword, bukan pure digit
        clean_tokens: list[str] = []
        for raw in query.split():
            t = re.sub(r"[^\w]", "", raw)        # hapus semua non-alphanumeric
            if len(t) >= 3 and t.lower() not in stopwords and not t.isdigit():
                clean_tokens.append(t)

        if not clean_tokens:
            return []

        loop = asyncio.get_event_loop()
        seen_ids: set[str] = set()
        merged:   list[dict] = []

        for token in clean_tokens:
            try:
                r = await loop.run_in_executor(
                    None,
                    lambda t=token: self._collection.get(
                        where_document={"$contains": t},
                        limit=n_results,          # ← TIER 1 FIX: batasi hasil per token
                        include=["metadatas"],
                    ),
                )
                for meta in (r.get("metadatas") or []):
                    pid = meta.get("id", "")
                    if pid not in seen_ids:
                        seen_ids.add(pid)
                        merged.append({**meta, "_sim": 0.95})  # keyword hit = high confidence
            except Exception as e:
                print(f"[RAG] keyword_search error for '{token}': {e}")

        return merged[:n_results]

    def count(self) -> int:
        return self._collection.count()


_vector_store: ProductVectorStore | None = None


async def _fetch_all_products() -> list[dict]:
    """Ambil semua produk dari Laravel API (dengan pagination)."""
    all_products: list[dict] = []
    page = 1
    while True:
        try:
            async with httpx.AsyncClient(timeout=httpx.Timeout(30.0)) as client:
                r = await client.get(
                    f"{LARAVEL_API_URL}/api/product",
                    params={"limit": 500, "page": page},   # 500 per halaman — ambil semua sekaligus
                )
            if r.status_code != 200:
                break
            data     = r.json()
            products = data.get("data", [])
            if not products:
                break

            for p in products:
                store    = p.get("store") or {}
                category = p.get("product_category") or {}
                all_products.append({
                    "id":          str(p.get("id", "")),
                    "slug":        p.get("slug", ""),
                    "name":        p.get("name", ""),
                    "price":       p.get("price", 0),
                    "thumbnail":   p.get("thumbnail") or "",
                    "description": p.get("description", ""),
                    "condition":   p.get("condition", ""),
                    "stock":       p.get("stock", 0),
                    "total_sold":  p.get("total_sold", 0),
                    "store":       store.get("name", "") if isinstance(store, dict) else "",
                    "category":    category.get("name", "") if isinstance(category, dict) else "",
                })

            if not data.get("next_page_url"):
                break
            page += 1
        except Exception as e:
            print(f"[RAG] Error fetch halaman {page}: {e}")
            break

    return all_products


async def _rag_refresh_loop() -> None:
    """Background task: re-index produk dari Laravel setiap RAG_REFRESH_HOURS jam."""
    while True:
        await asyncio.sleep(RAG_REFRESH_HOURS * 3600)
        if _vector_store is None:
            continue
        try:
            count = await _vector_store.build_index()
            print(f"[RAG] Refresh: {count} produk.")
        except Exception as e:
            print(f"[RAG] Refresh error: {e}")


# ---------------------------------------------------------------------------
# LIFESPAN
# ---------------------------------------------------------------------------
@asynccontextmanager
async def lifespan(application: FastAPI):
    global _redis, _vector_store

    # Redis
    _redis = aioredis.from_url(REDIS_URL, decode_responses=True)
    try:
        await _redis.ping()  # type: ignore[misc]  # redis-py 7.x stubs incorrectly typed
        print(f"[Startup] Redis connected: {REDIS_URL}")
    except Exception as e:
        print(f"[Startup] WARNING: Redis tidak bisa dikoneksi: {e}")

    # ChromaDB + RAG index (non-fatal jika gagal)
    _vector_store = ProductVectorStore()
    try:
        count = await _vector_store.build_index()
        print(f"[Startup] RAG index ready: {count} produk")
    except Exception as e:
        print(f"[Startup] WARNING: RAG indexing gagal: {e}. Akan retry di background.")

    # Background task: refresh index setiap N jam
    refresh_task = asyncio.create_task(_rag_refresh_loop())

    yield

    refresh_task.cancel()
    await _redis.aclose()
    print("[Shutdown] Cleanup selesai.")


# ---------------------------------------------------------------------------
# RATE LIMITER & APP
# ---------------------------------------------------------------------------
limiter = Limiter(key_func=get_remote_address)

app = FastAPI(lifespan=lifespan)
app.state.limiter = limiter
app.add_exception_handler(RateLimitExceeded, _rate_limit_exceeded_handler)
app.add_middleware(
    CORSMiddleware,
    allow_origins=CORS_ALLOWED_ORIGINS,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# ---------------------------------------------------------------------------
# METRICS
# ---------------------------------------------------------------------------
REQUEST_COUNT = Counter(
    "http_requests_total", "Total HTTP requests",
    ["method", "endpoint", "status"],
)
REQUEST_LATENCY = Histogram(
    "http_request_duration_seconds", "HTTP latency",
    ["endpoint"],
)

# RAG + Feedback evaluation metrics
FEEDBACK_RATING = Histogram(
    "chatbot_feedback_rating",
    "Distribusi rating feedback user (1-5)",
    buckets=[1, 2, 3, 4, 5],
)
FEEDBACK_TOTAL = Counter(
    "chatbot_feedback_total",
    "Total feedback yang diterima",
)
RAG_INDEXED = Gauge(
    "rag_products_indexed",
    "Jumlah produk yang terindex di ChromaDB",
)
RAG_SEARCH_HITS = Counter(
    "rag_search_hits_total",
    "Search yang menemukan produk relevan (sim >= threshold)",
)
RAG_SEARCH_MISSES = Counter(
    "rag_search_misses_total",
    "Search yang tidak menemukan produk relevan",
)


@app.middleware("http")
async def metrics_middleware(request: Request, call_next):
    start = time.perf_counter()
    response = await call_next(request)
    REQUEST_LATENCY.labels(endpoint=request.url.path).observe(time.perf_counter() - start)
    REQUEST_COUNT.labels(
        method=request.method,
        endpoint=request.url.path,
        status=str(response.status_code),
    ).inc()
    return response


# ---------------------------------------------------------------------------
# SCHEMA
# ---------------------------------------------------------------------------
class ChatRequest(BaseModel):
    message:    str       = Field(..., min_length=1, max_length=1000)
    session_id: str | None = Field(default=None)


class ChatResponse(BaseModel):
    reply:      str
    status:     str
    session_id: str
    products:   list[dict] | None = None   # produk relevan dari RAG (opsional)


class FeedbackRequest(BaseModel):
    session_id: str
    rating:     int       = Field(..., ge=1, le=5)
    comment:    str | None = Field(default=None, max_length=500)


# ---------------------------------------------------------------------------
# SYSTEM PROMPT
# ---------------------------------------------------------------------------
SYSTEM_PROMPT = (
    "Kamu adalah Ri, asisten virtual Blukios yang ramah, sopan, dan profesional. "
    "Blukios adalah marketplace multi-vendor yang menjual gadget, elektronik, dan aksesoris berkualitas. "
    "Gunakan bahasa Indonesia yang santai tapi tetap profesional. "
    "Tugasmu adalah:\n"
    "1. Membantu pengguna mencari dan menemukan produk yang mereka butuhkan.\n"
    "2. Menjawab pertanyaan seputar produk, harga, spesifikasi, dan ketersediaan.\n"
    "3. Memberikan rekomendasi singkat berdasarkan kebutuhan pengguna.\n"
    "4. Menjelaskan kebijakan marketplace seperti pembayaran, pengiriman, dan pengembalian.\n"
    "PENTING — Jika ada data produk yang disediakan:\n"
    "- Jangan list semua detail produk (harga, toko, stok) secara lengkap di dalam teks jawabanmu.\n"
    "- Frontend sudah menampilkan kartu produk dengan detail lengkap secara otomatis.\n"
    "- Cukup sebutkan nama produk dan berikan rekomendasi singkat mengapa produk itu cocok.\n"
    "- Jawaban maksimal 3-4 kalimat. Jangan gunakan format markdown (###, **, --).\n"
    "Jika tidak ada produk relevan, jawab pertanyaan umum secara singkat."
)


# ---------------------------------------------------------------------------
# OLLAMA CLIENT — Non-Streaming
# ---------------------------------------------------------------------------
async def _ollama_chat(messages: list[dict], temperature: float | None = None) -> str:
    payload: dict[str, Any] = {
        "model":   OLLAMA_MODEL,
        "messages": messages,
        "stream":  False,
    }
    if temperature is not None:
        payload["options"] = {"temperature": temperature}

    last_error: Exception | None = None
    for attempt in range(OLLAMA_MAX_RETRIES + 1):
        try:
            async with httpx.AsyncClient(timeout=httpx.Timeout(OLLAMA_TIMEOUT_S)) as client:
                r = await client.post(f"{OLLAMA_BASE_URL}/api/chat", json=payload)
            if r.status_code >= 400:
                raise RuntimeError(f"Ollama error {r.status_code}: {r.text[:200]}")
            content = r.json().get("message", {}).get("content")
            if not content:
                raise RuntimeError("Ollama response missing content")
            return content
        except (httpx.TimeoutException, httpx.NetworkError, RuntimeError) as err:
            last_error = err
            if attempt < OLLAMA_MAX_RETRIES:
                await asyncio.sleep(0.5 * (attempt + 1))
    raise last_error or RuntimeError("Unknown Ollama error")


# ---------------------------------------------------------------------------
# OLLAMA CLIENT — Streaming
# ---------------------------------------------------------------------------
async def _ollama_stream(messages: list[dict], temperature: float = 0.7):
    payload: dict[str, Any] = {
        "model":    OLLAMA_MODEL,
        "messages": messages,
        "stream":   True,
        "options":  {"temperature": temperature},
    }
    async with httpx.AsyncClient(timeout=httpx.Timeout(OLLAMA_TIMEOUT_S)) as client:
        async with client.stream("POST", f"{OLLAMA_BASE_URL}/api/chat", json=payload) as r:
            if r.status_code >= 400:
                body = await r.aread()
                raise RuntimeError(f"Ollama stream error {r.status_code}: {body.decode()}")
            async for line in r.aiter_lines():
                if not line.strip():
                    continue
                try:
                    chunk = json.loads(line)
                except json.JSONDecodeError:
                    continue
                token: str  = chunk.get("message", {}).get("content", "")
                done:  bool = chunk.get("done", False)
                yield token, done
                if done:
                    break


# ---------------------------------------------------------------------------
# HELPERS
# ---------------------------------------------------------------------------
def _build_product_context(products: list[dict]) -> str:
    """Bangun context string dari hasil RAG search untuk diinjeksi ke LLM."""
    if not products:
        return ""   # Pertanyaan umum — jangan inject apapun

    lines = "\n".join([
        f"- {p['name']} | Kategori: {p.get('category','')} | Kondisi: {p.get('condition','')}"
        for p in products
    ])
    return (
        f"\n[SISTEM: Produk yang tersedia di Blukios sesuai pertanyaan user (detail harga & toko sudah ditampilkan sebagai kartu di UI):\n"
        f"{lines}\n"
        f"Berikan rekomendasi SINGKAT — sebutkan nama produk dan alasan singkat mengapa cocok. "
        f"Jangan ulangi harga atau detail toko karena sudah ada di kartu produk.]"
    )


async def _prepare_context(session_id: str, user_msg: str) -> tuple[list[dict], list[dict]]:
    """
    Hybrid RAG + session history — dijalankan PARALEL via asyncio.gather.
    1. Intent detection: skip RAG jika query adalah sapaan/chat umum
    2. Semantic search: temukan produk berdasarkan makna (vector similarity)
    3. Keyword search: temukan produk berdasarkan nama/merek (substring match)
    4. Merge kedua hasil — keyword hits diprioritaskan
    5. Ambil history percakapan (Redis)
    """
    async def _empty() -> list[dict]:
        return []

    # Tier 2: Intent detection — skip RAG untuk sapaan/query umum
    is_general = _is_general_query(user_msg)

    if _vector_store and not is_general:
        # Query rewriting: bersihkan noise sebelum masuk embedding
        rewritten    = _rewrite_query(user_msg)
        meta_filter  = _extract_metadata_filters(user_msg)

        if rewritten != user_msg:
            print(f"[RAG] Rewrite: '{user_msg[:40]}' → '{rewritten}'")
        if meta_filter:
            print(f"[RAG] Metadata filter: {meta_filter}")

        semantic_task = _vector_store.search(rewritten, where=meta_filter)
        keyword_task  = _vector_store.keyword_search(user_msg)  # keyword pakai query asli
    else:
        semantic_task = _empty()
        keyword_task  = _empty()
        if is_general:
            print(f"[RAG] Skipping product search — detected general query: '{user_msg[:40]}'")

    semantic_results, keyword_results, history = await asyncio.gather(
        semantic_task, keyword_task, get_session_history(session_id)
    )

    # Merge: keyword hits lebih reliable untuk name queries, semantic untuk intent queries
    seen_ids: set[str] = set()
    products: list[dict] = []

    # Keyword hits first (high precision)
    for p in keyword_results:
        pid = p.get("id", "")
        if pid not in seen_ids:
            seen_ids.add(pid)
            products.append(p)

    # Semantic results sebagai supplement (jika belum ada)
    for p in semantic_results:
        pid = p.get("id", "")
        if pid not in seen_ids:
            seen_ids.add(pid)
            products.append(p)

    # Batasi ke RAG_TOP_K
    products = products[:RAG_TOP_K]

    context_info       = _build_product_context(products)
    final_user_content = f"{user_msg} {context_info}".strip()

    messages = [
        {"role": "system", "content": SYSTEM_PROMPT},
        *history,
        {"role": "user", "content": final_user_content},
    ]

    sim_scores = [p.get("_sim", 0) for p in products]
    kw_count   = len(keyword_results)
    sem_count  = len(semantic_results)
    print(f"[RAG] session={session_id} keyword={kw_count} semantic={sem_count} merged={len(products)} sim={sim_scores}")

    # Prometheus
    if products:
        RAG_SEARCH_HITS.inc()
    else:
        RAG_SEARCH_MISSES.inc()

    return products, messages


# ---------------------------------------------------------------------------
# ENDPOINT: /predict
# ---------------------------------------------------------------------------
@app.post("/predict", response_model=ChatResponse)
@limiter.limit(f"{RATE_LIMIT_PER_MINUTE}/minute")
async def predict_response(request: Request, body: ChatRequest) -> ChatResponse:
    session_id = body.session_id or str(uuid.uuid4())
    reply_text = ""
    had_error  = False

    try:
        found_products, messages = await _prepare_context(session_id, body.message)

        # Tier 2: LLM Cache — cek Redis sebelum hit Ollama
        cache_key  = _make_cache_key(body.message, session_id)
        cached_reply = await get_llm_cache(cache_key)
        if cached_reply:
            print(f"[Cache] HIT session={session_id}")
            reply_text = cached_reply
        else:
            reply_text = await _ollama_chat(messages, temperature=0.7)
            await set_llm_cache(cache_key, reply_text)

        await append_session_history(session_id, body.message, reply_text)
    except Exception as e:
        reply_text = "Duh, Ri lagi pusing nih karena ada error di server, coba tanya lagi nanti ya~"
        print(f"[Predict] Error: {e}")
        had_error = True
        found_products = []

    # Strip internal field _sim sebelum dikirim ke frontend
    clean_products = [{k: v for k, v in p.items() if k != "_sim"} for p in found_products]

    return ChatResponse(
        reply=reply_text,
        status="error" if had_error else "success",
        session_id=session_id,
        products=clean_products or None,
    )


# ---------------------------------------------------------------------------
# ENDPOINT: /predict/stream
# ---------------------------------------------------------------------------
@app.post("/predict/stream")
@limiter.limit(f"{RATE_LIMIT_PER_MINUTE}/minute")
async def predict_stream(request: Request, body: ChatRequest) -> StreamingResponse:
    session_id = body.session_id or str(uuid.uuid4())

    async def _event_generator():
        full_reply_parts: list[str] = []
        found_products:   list[dict] = []
        had_error = False

        try:
            found_products, messages = await _prepare_context(session_id, body.message)

            # Tier 2: LLM Cache — jika cache hit, stream kata per kata tanpa hit Ollama
            cache_key    = _make_cache_key(body.message, session_id)
            cached_reply = await get_llm_cache(cache_key)
            if cached_reply:
                print(f"[Cache] STREAM HIT session={session_id}")
                # Simulasi streaming dari cache — kirim token per ~5 kata
                words = cached_reply.split()
                chunk_size = 5
                for i in range(0, len(words), chunk_size):
                    token = " ".join(words[i:i + chunk_size]) + (" " if i + chunk_size < len(words) else "")
                    yield f"data: {json.dumps({'token': token, 'done': False, 'session_id': session_id})}\n\n"
                # Done event dengan products
                clean = [{k: v for k, v in p.items() if k != "_sim"} for p in found_products]
                yield f"data: {json.dumps({'token': '', 'done': True, 'session_id': session_id, 'products': clean})}\n\n"
                return

            # Cache miss — stream dari Ollama
            async for token, done in _ollama_stream(messages, temperature=0.7):
                if token:
                    full_reply_parts.append(token)

                if done:
                    # Hanya kirim field minimum ke frontend — hindari payload besar yang menyebabkan TCP split
                    clean = [
                        {
                            "id":        p.get("id", ""),
                            "slug":      p.get("slug", ""),
                            "name":      p.get("name", ""),
                            "price":     p.get("price", 0),
                            "thumbnail": p.get("thumbnail", ""),
                            "store":     p.get("store", ""),
                            "category":  p.get("category", ""),
                            "condition": p.get("condition", ""),
                            "stock":     p.get("stock", 0),
                        }
                        for p in found_products
                    ]
                    event_data = json.dumps(
                        {"token": "", "done": True, "session_id": session_id, "products": clean},
                        ensure_ascii=False,
                    )
                    # Simpan ke cache setelah stream selesai
                    await set_llm_cache(cache_key, "".join(full_reply_parts))
                    yield f"data: {event_data}\n\n"
                    break
                elif token:
                    # Hanya kirim event jika token non-empty (skip empty thinking chunks dari qwen3)
                    event_data = json.dumps(
                        {"token": token, "done": False, "session_id": session_id},
                        ensure_ascii=False,
                    )
                    yield f"data: {event_data}\n\n"

        except Exception as e:
            print(f"[Stream] Error: {e}")
            had_error = True
            yield f"data: {json.dumps({'token': 'Duh, Ri lagi error~', 'done': True, 'session_id': session_id, 'error': True}, ensure_ascii=False)}\n\n"

        finally:
            if full_reply_parts and not had_error:
                await append_session_history(session_id, body.message, "".join(full_reply_parts))

    return StreamingResponse(
        _event_generator(),
        media_type="text/event-stream",
        headers={"X-Accel-Buffering": "no", "Cache-Control": "no-cache"},
    )


# ---------------------------------------------------------------------------
# ENDPOINT: /feedback
# ---------------------------------------------------------------------------
@app.post("/feedback", status_code=202)
@limiter.limit("10/minute")
async def submit_feedback(request: Request, body: FeedbackRequest) -> dict:
    try:
        feedback = {
            "session_id": body.session_id,
            "rating":     body.rating,
            "comment":    body.comment,
            "timestamp":  datetime.now(timezone.utc).isoformat(),
        }
        key = f"{_FEEDBACK_KEY}{body.session_id}:{int(time.time())}"
        await _get_redis().setex(key, 30 * 24 * 3600, json.dumps(feedback, ensure_ascii=False))

        # Record ke Prometheus
        FEEDBACK_RATING.observe(body.rating)
        FEEDBACK_TOTAL.inc()

        print(f"[Feedback] session={body.session_id} rating={body.rating}")
    except Exception as e:
        print(f"[Feedback] Error: {e}")
        return {"status": "error", "message": "Gagal menyimpan feedback"}
    return {"status": "received"}


# ---------------------------------------------------------------------------
# ENDPOINT: /admin/reindex  — Trigger manual RAG re-index tanpa restart
# ---------------------------------------------------------------------------
@app.post("/admin/reindex")
async def admin_reindex() -> dict:
    """Trigger re-indexing semua produk dari Laravel API ke ChromaDB."""
    if _vector_store is None:
        return {"status": "error", "message": "Vector store belum diinisialisasi"}
    try:
        count = await _vector_store.build_index()
        print(f"[Admin] Manual reindex: {count} produk")
        return {"status": "ok", "indexed": count}
    except Exception as e:
        print(f"[Admin] Reindex error: {e}")
        return {"status": "error", "message": str(e)}


# ---------------------------------------------------------------------------
# ENDPOINT: /chat/reset  — Flush session history user dari Redis
# ---------------------------------------------------------------------------
@app.delete("/chat/reset/{session_id}")
async def chat_reset(session_id: str) -> dict:
    """Hapus history percakapan untuk session tertentu."""
    try:
        key = f"{_SESSION_KEY}{session_id}"
        await _get_redis().delete(key)
        print(f"[Chat] Reset session: {session_id}")
        return {"status": "ok", "session_id": session_id}
    except Exception as e:
        print(f"[Chat] Reset error: {e}")
        return {"status": "error", "message": str(e)}


# ---------------------------------------------------------------------------
# ENDPOINT: /metrics
# ---------------------------------------------------------------------------
@app.get("/metrics")
def metrics():
    return Response(content=generate_latest(), media_type=CONTENT_TYPE_LATEST)


# ---------------------------------------------------------------------------
# ENDPOINT: /eval  — Statistik evaluasi RAG + feedback dari Redis
# ---------------------------------------------------------------------------
@app.get("/eval")
async def eval_stats() -> dict:
    """
    Mengembalikan statistik evaluasi:
    - Jumlah produk terindex (RAG)
    - Total feedback, rata-rata rating, distribusi
    - RAG hit rate (% search yang menemukan produk relevan)
    """
    try:
        redis_client = _get_redis()

        # Scan semua feedback keys
        feedback_keys: list[str] = []
        async for key in redis_client.scan_iter(f"{_FEEDBACK_KEY}*"):
            feedback_keys.append(key)

        ratings: list[int] = []
        if feedback_keys:
            raw_values = await redis_client.mget(*feedback_keys)
            for raw in raw_values:
                if raw:
                    fb = json.loads(raw)
                    rating = fb.get("rating")
                    if isinstance(rating, (int, float)):
                        ratings.append(int(rating))

        distribution = {str(i): 0 for i in range(1, 6)}
        for rating_val in ratings:
            k = str(max(1, min(5, rating_val)))
            distribution[k] += 1

        avg_rating = round(sum(ratings) / len(ratings), 2) if ratings else None

        return {
            "rag": {
                "indexed_products":  _vector_store.count() if _vector_store else 0,
                "similarity_threshold": RAG_SIMILARITY_THRESHOLD,
                "top_k": RAG_TOP_K,
            },
            "feedback": {
                "total":        len(ratings),
                "avg_rating":   avg_rating,
                "distribution": distribution,
            },
        }
    except Exception as e:
        return {"error": str(e)}


# ---------------------------------------------------------------------------
# ENDPOINT: /debug/search  — Trace LENGKAP RAG pipeline termasuk vektor
# ---------------------------------------------------------------------------
@app.get("/debug/search")
async def debug_search(q: str, n: int = 10, show_vectors: bool = False) -> dict:
    """
    Trace RAG pipeline secara transparan, termasuk embedding vector.

    Parameters:
    - q             : teks query (contoh: "headset gaming")
    - n             : jumlah produk yang diambil dari ChromaDB (default 10)
    - show_vectors  : tampilkan vector lengkap 384 dimensi (default false)

    Contoh:
      GET /debug/search?q=headset+gaming
      GET /debug/search?q=headset+gaming&show_vectors=true
    """
    if not _vector_store:
        return {"error": "Vector store belum siap"}

    total_indexed = _vector_store.count()
    if total_indexed == 0:
        return {"error": "Index kosong — belum ada produk yang di-embed"}

    try:
        loop = asyncio.get_event_loop()

        # ----------------------------------------------------------------
        # STEP 1: Embed query → vector
        # all-MiniLM-L6-v2 menghasilkan 384 dimensi
        # ----------------------------------------------------------------
        query_vector: list[float] = await loop.run_in_executor(
            None, lambda: [float(v) for v in _vector_store._ef([q])[0]]
        )
        vector_dim = len(query_vector)

        # ----------------------------------------------------------------
        # STEP 2: ChromaDB cari produk paling mirip (cosine similarity)
        # Minta embeddings produk juga agar bisa ditampilkan
        # ----------------------------------------------------------------
        include_fields = ["metadatas", "distances", "documents"]
        if show_vectors:
            include_fields.append("embeddings")

        raw_results = await loop.run_in_executor(
            None,
            lambda: _vector_store._collection.query(
                query_texts=[q],
                n_results=min(n, total_indexed),
                include=include_fields,
            ),
        )

        # ----------------------------------------------------------------
        # STEP 3: Format hasil dengan penjelasan perhitungan similarity
        # Rumus: similarity = 1 - (cosine_distance / 2)
        # ChromaDB mengembalikan cosine distance [0, 2]
        # ----------------------------------------------------------------
        above: list[dict] = []
        below: list[dict] = []

        metadatas  = raw_results.get("metadatas",  [[]])[0]
        distances  = raw_results.get("distances",  [[]])[0]
        documents  = raw_results.get("documents",  [[]])[0]
        embeddings = raw_results.get("embeddings", [[]])[0] if show_vectors else []

        for rank, (meta, dist, doc) in enumerate(
            zip(metadatas, distances, documents), start=1
        ):
            similarity = max(0.0, round(1.0 - dist / 2.0, 6))
            entry: dict = {
                "rank":              rank,
                "name":              meta.get("name", ""),
                "category":          meta.get("category", ""),
                "price":             meta.get("price", 0),
                "store":             meta.get("store", ""),
                "condition":         meta.get("condition", ""),
                "stock":             meta.get("stock", 0),
                "lolos_threshold":   similarity >= RAG_SIMILARITY_THRESHOLD,
                # --- Similarity details ---
                "similarity_score":  similarity,
                "cosine_distance":   round(dist, 6),
                "formula":           f"1 - ({round(dist,4)} / 2) = {similarity}",
                # --- Teks yang dikonversi ke vector ---
                "embedded_text":     doc,
            }

            if show_vectors and len(embeddings) > 0:
                prod_vec = embeddings[rank - 1] if rank - 1 < len(embeddings) else []
                entry["product_vector"] = {
                    "dimensions": len(prod_vec),
                    "preview_first_8": [float(round(v, 6)) for v in prod_vec[:8]],
                    "full_vector": [float(round(v, 6)) for v in prod_vec],
                }

            if similarity >= RAG_SIMILARITY_THRESHOLD:
                above.append(entry)
            else:
                below.append(entry)

        # ----------------------------------------------------------------
        # STEP 4: Response lengkap
        # ----------------------------------------------------------------
        response: dict = {
            "pipeline": {
                "step_1_input":  f"'{q}'",
                "step_2_model":  "all-MiniLM-L6-v2 (384 dimensi)",
                "step_3_store":  "ChromaDB (cosine similarity)",
                "step_4_filter": f"threshold >= {RAG_SIMILARITY_THRESHOLD}",
                "step_5_output": f"{len(above)} produk diinjeksi ke qwen3:1.7b",
            },
            "query_embedding": {
                "text":           q,
                "dimensions":     vector_dim,
                "preview_first_8": [float(round(v, 6)) for v in query_vector[:8]],
                **({"full_vector": [float(round(v, 6)) for v in query_vector]} if show_vectors else {}),
            },
            "search_summary": {
                "total_indexed":   total_indexed,
                "top_k_requested": n,
                "above_threshold": len(above),
                "below_threshold": len(below),
                "threshold":       RAG_SIMILARITY_THRESHOLD,
            },
            "results_above_threshold": above,
            "results_below_threshold": below,
        }

        return response

    except Exception as e:
        return {"error": str(e)}
