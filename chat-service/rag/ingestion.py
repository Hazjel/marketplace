import httpx

from config import LARAVEL_API_URL


# ---------------------------------------------------------------------------
# Laravel API fetch
# ---------------------------------------------------------------------------
async def fetch_all_products() -> list[dict]:
    """Ambil semua produk dari Laravel API (dengan pagination)."""
    all_products: list[dict] = []
    page = 1
    async with httpx.AsyncClient(timeout=httpx.Timeout(30.0)) as client:
        while True:
            try:
                r = await client.get(
                    f"{LARAVEL_API_URL}/api/product",
                    params={"limit": 500, "page": page},
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
