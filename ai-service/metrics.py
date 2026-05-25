from prometheus_client import Counter, Gauge, Histogram

# ---------------------------------------------------------------------------
# HTTP Metrics
# ---------------------------------------------------------------------------
REQUEST_COUNT = Counter(
    "http_requests_total", "Total HTTP requests",
    ["method", "endpoint", "status"],
)
REQUEST_LATENCY = Histogram(
    "http_request_duration_seconds", "HTTP latency",
    ["endpoint"],
)

# ---------------------------------------------------------------------------
# Feedback Metrics
# ---------------------------------------------------------------------------
FEEDBACK_RATING = Histogram(
    "chatbot_feedback_rating",
    "Distribusi rating feedback user (1-5)",
    buckets=[1, 2, 3, 4, 5],
)
FEEDBACK_TOTAL = Counter(
    "chatbot_feedback_total",
    "Total feedback yang diterima",
)

# ---------------------------------------------------------------------------
# RAG Metrics
# ---------------------------------------------------------------------------
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
