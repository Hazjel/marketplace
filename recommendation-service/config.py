import os
from dotenv import load_dotenv

load_dotenv(override=False)

# ---------------------------------------------------------------------------
# LARAVEL API
# ---------------------------------------------------------------------------
LARAVEL_API_URL = os.getenv("LARAVEL_API_URL", "http://localhost:8000").rstrip("/")

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
