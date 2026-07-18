import asyncio
import re

import chromadb
from chromadb.utils.embedding_functions import DefaultEmbeddingFunction

from config import (
    CHROMA_DB_PATH,
    RAG_SIMILARITY_THRESHOLD,
    RAG_TOP_K,
)
from rag.ingestion import fetch_all_products

# ---------------------------------------------------------------------------
# Singleton — diinisialisasi oleh lifespan di main.py
# ---------------------------------------------------------------------------
_vector_store: "ProductVectorStore | None" = None


def init_vector_store(vs: "ProductVectorStore") -> None:
    """Dipanggil sekali saat startup oleh lifespan."""
    global _vector_store
    _vector_store = vs


def get_vector_store() -> "ProductVectorStore | None":
    return _vector_store


# ---------------------------------------------------------------------------
# ProductVectorStore
# ---------------------------------------------------------------------------
class ProductVectorStore:
    """Menyimpan dan mencari produk menggunakan vector similarity."""

    # Brand keywords yang perlu di-boost dalam embedding
    _KNOWN_BRANDS: frozenset[str] = frozenset({
        "asus", "samsung", "apple", "xiaomi", "oppo", "vivo", "realme",
        "logitech", "sony", "jbl", "anker", "baseus", "razer", "acer",
        "lenovo", "lg", "huawei", "nokia", "dell", "msi", "corsair",
        "keychron", "rexus", "sennheiser", "bose", "nintendo", "cosrx",
    })

    def __init__(self) -> None:
        # all-MiniLM-L6-v2 via onnxruntime — tidak perlu model Ollama terpisah
        self._ef         = DefaultEmbeddingFunction()
        self._client     = chromadb.PersistentClient(path=CHROMA_DB_PATH)
        self._collection = self._client.get_or_create_collection(
            name="products",
            embedding_function=self._ef,
            metadata={"hnsw:space": "cosine"},
        )

    @staticmethod
    def _product_to_text(p: dict) -> str:
        """
        Bangun teks representasi produk untuk embedding.
        Brand name di-boost dengan pengulangan agar similarity lebih kuat.
        """
        name      = p.get("name", "")
        category  = p.get("category", "")
        desc      = str(p.get("description", ""))[:300]
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
        products = await fetch_all_products()
        if not products:
            print("[RAG] Tidak ada produk untuk di-index.")
            return 0

        print(f"[RAG] Embedding {len(products)} produk via onnxruntime...")
        texts     = [self._product_to_text(p) for p in products]
        ids       = [str(p.get("id", i)) for i, p in enumerate(products)]
        metadatas = [
            {
                "id":          p.get("id", ""),
                "slug":        p.get("slug", ""),
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
            lambda: self._collection.upsert(ids=ids, documents=texts, metadatas=metadatas),
        )

        count = self._collection.count()
        print(f"[RAG] Index selesai: {count} produk tersimpan.")
        return count

    async def search(
        self,
        query:     str,
        n_results: int         = RAG_TOP_K,
        threshold: float       = RAG_SIMILARITY_THRESHOLD,
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
                    where=where or None,
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

        stopwords = {
            "ada", "yang", "apa", "apakah", "dengan", "untuk", "atau",
            "dan", "di", "ke", "dari", "adalah", "ini", "itu", "saya",
            "produk", "bernama", "nama", "beli", "mau", "cari", "tolong",
            "boleh", "bisa", "minta", "butuh", "perlu", "ingin", "want",
            "please", "show", "find", "search", "get", "list",
            "saja", "tersedia", "marketplace", "semua", "berapa",
            "harga", "aja", "kira", "sekitar", "gimana", "bagaimana",
            "toko", "blukios", "sini", "sana", "dimana", "siapa",
            "kapan", "kenapa", "mengapa", "mohon", "dong", "deh",
        }
        clean_tokens: list[str] = []
        for raw in query.split():
            t = re.sub(r"[^\w]", "", raw)
            if len(t) >= 3 and t.lower() not in stopwords and not t.isdigit():
                clean_tokens.append(t)

        if not clean_tokens:
            return []

        loop = asyncio.get_event_loop()

        async def _search_token(t: str) -> list[dict]:
            try:
                r = await loop.run_in_executor(
                    None,
                    lambda: self._collection.get(
                        where_document={"$contains": t},
                        limit=n_results,
                        include=["metadatas"],
                    ),
                )
                return r.get("metadatas") or []
            except Exception:
                return []

        all_results = await asyncio.gather(*[_search_token(t) for t in clean_tokens])

        seen_ids:   set[str] = set()
        merged:   list[dict] = []
        for metas in all_results:
            for meta in metas:
                pid = str(meta.get("id", ""))
                if pid and pid not in seen_ids:
                    seen_ids.add(pid)
                    merged.append(meta)
                    if len(merged) >= n_results:
                        return merged

        return merged

    def count(self) -> int:
        return self._collection.count()
