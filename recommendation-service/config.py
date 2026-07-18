import os
from dotenv import load_dotenv

load_dotenv(override=False)

# ---------------------------------------------------------------------------
# LARAVEL API
# ---------------------------------------------------------------------------
LARAVEL_API_URL     = os.getenv("LARAVEL_API_URL", "http://localhost:8000").rstrip("/")
INTERNAL_SERVICE_KEY = os.getenv("INTERNAL_SERVICE_KEY", "")

# ---------------------------------------------------------------------------
# CORS
# ---------------------------------------------------------------------------
CORS_ALLOWED_ORIGINS: list[str] = [
    o.strip()
    for o in os.getenv(
        "CORS_ALLOWED_ORIGINS",
        "http://localhost:3000,http://localhost:5173,http://localhost:8080",
    ).split(",")
    if o.strip()
]

# ---------------------------------------------------------------------------
# CONTENT-BASED FILTERING
# ---------------------------------------------------------------------------
CBF_TOP_K = int(os.getenv("CBF_TOP_K", "8"))

# ---------------------------------------------------------------------------
# PRODUCT CACHE
# ---------------------------------------------------------------------------
PRODUCT_CACHE_REFRESH_MINUTES = int(os.getenv("PRODUCT_CACHE_REFRESH_MINUTES", "30"))

# ---------------------------------------------------------------------------
# COLLABORATIVE FILTERING (SVD)
# ---------------------------------------------------------------------------
CF_TOP_K = int(os.getenv("CF_TOP_K", "8"))
# minimal interaksi (baris user_id-product_id) sebelum model SVD dianggap
# cukup data buat dilatih -- di bawah ini, sistem full fallback ke CBF
# (data terlalu sparse, hasil SVD gak reliable / cold-start)
CF_MIN_INTERACTIONS = int(os.getenv("CF_MIN_INTERACTIONS", "50"))
# minimal user unik yang punya >=2 interaksi -- SVD butuh pola lintas-user
# buat belajar, bukan cuma banyak baris dari 1-2 user aktif doang
CF_MIN_ACTIVE_USERS = int(os.getenv("CF_MIN_ACTIVE_USERS", "5"))
CF_RETRAIN_INTERVAL_HOURS = int(os.getenv("CF_RETRAIN_INTERVAL_HOURS", "24"))
CF_MODEL_PATH = os.getenv("CF_MODEL_PATH", "/app/models/svd_model.pkl")
