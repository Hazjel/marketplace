import httpx

from config import LARAVEL_API_URL


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
                    category = p.get("product_category") or {}
                    all_products.append({
                        "id":               str(p.get("id", "")),
                        "slug":             p.get("slug", ""),
                        "name":             p.get("name", ""),
                        "price":            float(p.get("price", 0)),
                        "thumbnail":        p.get("thumbnail") or "",
                        "condition":        p.get("condition", ""),
                        "stock":            int(p.get("stock", 0)),
                        "total_sold":       int(p.get("total_sold", 0)),
                        "rating":           float(p.get("rating", 0) or 0),
                        "category_id":      category.get("id", "") if isinstance(category, dict) else "",
                        "category":         category.get("name", "") if isinstance(category, dict) else "",
                    })

                if not data.get("next_page_url"):
                    break
                page += 1
            except Exception as e:
                print(f"[Recommendation] Error fetch halaman {page}: {e}")
                break

    return all_products
