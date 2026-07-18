from prometheus_client import Counter, Gauge, Histogram

# ---------------------------------------------------------------------------
# HTTP Metrics
# ---------------------------------------------------------------------------
REQUEST_COUNT = Counter(
    "reco_http_requests_total", "Total HTTP requests",
    ["method", "endpoint", "status"],
)
REQUEST_LATENCY = Histogram(
    "reco_http_request_duration_seconds", "HTTP latency",
    ["endpoint"],
)

# ---------------------------------------------------------------------------
# Product Cache Metrics
# ---------------------------------------------------------------------------
PRODUCTS_CACHED = Gauge(
    "reco_products_cached",
    "Jumlah produk yang tersimpan di cache in-memory",
)

# ---------------------------------------------------------------------------
# CBF Metrics
# ---------------------------------------------------------------------------
CBF_REQUESTS = Counter(
    "reco_cbf_requests_total",
    "Total request rekomendasi produk serupa (content-based)",
)
CBF_EMPTY_RESULTS = Counter(
    "reco_cbf_empty_results_total",
    "Request CBF yang gak nemu kandidat serupa sama sekali",
)
