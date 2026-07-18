import asyncio

from config import PRODUCT_CACHE_REFRESH_MINUTES
from utils.ingestion import fetch_all_products

# ---------------------------------------------------------------------------
# Cache produk in-memory -- disegarkan berkala oleh product_cache_refresh_loop
# ---------------------------------------------------------------------------
_products: list[dict] = []


def get_cached_products() -> list[dict]:
    return _products


async def refresh_product_cache() -> int:
    global _products
    products = await fetch_all_products()
    _products = products
    return len(products)


async def product_cache_refresh_loop() -> None:
    """Background task: refresh cache produk dari Laravel setiap N menit."""
    while True:
        try:
            count = await refresh_product_cache()
            print(f"[Recommendation] Product cache refreshed: {count} produk.")
        except Exception as e:
            print(f"[Recommendation] Product cache refresh error: {e}")
        await asyncio.sleep(PRODUCT_CACHE_REFRESH_MINUTES * 60)
