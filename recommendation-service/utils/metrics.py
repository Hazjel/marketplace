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

# ---------------------------------------------------------------------------
# CF (Collaborative Filtering / SVD) Metrics
# ---------------------------------------------------------------------------
CF_MODEL_TRAINED = Gauge(
    "reco_cf_model_trained",
    "1 kalau model SVD lagi aktif (data cukup), 0 kalau fallback penuh ke CBF",
)
CF_TRAINING_INTERACTIONS = Gauge(
    "reco_cf_training_interactions",
    "Jumlah baris interaksi yang dipakai buat training terakhir",
)
CF_REQUESTS = Counter(
    "reco_cf_requests_total",
    "Total request rekomendasi personalisasi (collaborative filtering)",
)
CF_FALLBACK_TO_CBF = Counter(
    "reco_cf_fallback_total",
    "Request personalisasi yang fallback ke CBF (user baru/model belum siap)",
)
