import httpx

from config import INTERNAL_SERVICE_KEY, LARAVEL_API_URL


async def fetch_interactions() -> list[dict]:
    """
    Ambil semua data interaksi (purchase/review/wishlist/view) dari endpoint
    internal Laravel -- dipakai buat training model collaborative filtering.
    """
    async with httpx.AsyncClient(timeout=httpx.Timeout(30.0)) as client:
        try:
            r = await client.get(
                f"{LARAVEL_API_URL}/api/internal/interactions",
                headers={"X-Internal-Key": INTERNAL_SERVICE_KEY},
            )
            if r.status_code != 200:
                print(f"[Recommendation] Fetch interactions gagal: HTTP {r.status_code}")
                return []
            return r.json().get("data", [])
        except Exception as e:
            print(f"[Recommendation] Error fetch interactions: {e}")
            return []
