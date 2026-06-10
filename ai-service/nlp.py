import re
import hashlib


# ---------------------------------------------------------------------------
# Layer 1: Sapaan/closing eksplisit — match exact, langsung skip RAG
# ---------------------------------------------------------------------------
_GREETING_PATTERN = re.compile(
    r"^(halo|hai|hi|hey|hello|selamat|pagi|siang|malam|sore|apa kabar|makasih|terima kasih"
    r"|thanks|thank you|oke|ok|baik|siap|mantap|keren|lanjut|done|selesai|noted|sip)[\s!?.]*$",
    re.IGNORECASE,
)

# ---------------------------------------------------------------------------
# Layer 2: Token-based product intent scoring
# Jika query mengandung >= 1 kata ini, PASTI butuh RAG
# ---------------------------------------------------------------------------
_PRODUCT_INTENT_TOKENS = {
    # Aksi belanja
    "beli", "pesan", "order", "bayar", "checkout", "keranjang", "cart",
    # Atribut produk
    "harga", "price", "diskon", "promo", "murah", "mahal", "stok", "stock",
    "spesifikasi", "spek", "spec", "garansi", "warranty",
    # Query produk
    "produk", "barang", "item", "cari", "rekomendasi", "saran", "pilih",
    "laptop", "hp", "handphone", "smartphone", "tablet", "ipad", "iphone",
    "macbook", "airpods", "headset", "earphone",
    "mouse", "keyboard", "monitor", "kamera", "camera", "charger", "kabel",
    "gaming", "wireless", "bluetooth", "ram", "ssd", "processor", "gpu",
    "tersedia",
    # Toko & pengiriman
    "toko", "seller", "pengiriman", "ongkir", "kirim", "ekspedisi",
    # Kondisi
    "baru", "bekas", "second", "refurbished",
    # Brand umum (lowercase)
    "asus", "samsung", "apple", "xiaomi", "oppo", "vivo", "realme",
    "logitech", "sony", "jbl", "anker", "baseus", "razer", "acer", "lenovo",
    "lg", "huawei", "nokia", "google", "dell", "msi", "corsair",
}

# ---------------------------------------------------------------------------
# Mapping natural language → nama kategori di database
# ---------------------------------------------------------------------------
CATEGORY_MAP: dict[str, str] = {
    "laptop": "Laptop",     "notebook": "Laptop",     "komputer": "Laptop",
    "smartphone": "Smartphone", "handphone": "Smartphone", "ponsel": "Smartphone",
    "headset": "Aksesoris gadget", "earphone": "Aksesoris gadget", "tws": "Aksesoris gadget",
    "mouse": "Aksesoris gadget", "keyboard": "Aksesoris gadget",
    "monitor": "Aksesoris gadget", "aksesoris": "Aksesoris gadget",
    "smartwatch": "Aksesoris gadget", "wearable": "Aksesoris gadget",
    "elektronik": "Elektronik",  "electronic": "Elektronik",
    "skincare": "Skincare",      "perawatan": "Skincare",
}


# ---------------------------------------------------------------------------
# Intent Detection
# ---------------------------------------------------------------------------
def is_general_query(msg: str) -> bool:
    """
    Dua lapis intent detection:
    1. Regex layer: tangkap sapaan/closing eksplisit secara exact
    2. Token scoring: jika tidak ada satu pun product-intent token → skip RAG

    Contoh skip RAG:
      'halo!'              → layer 1 match
      'apakah blukios aman?' → layer 2: tidak ada token produk
      'bagaimana cara pembayaran?' → layer 2: tidak ada token produk

    Contoh TIDAK skip (tetap jalankan RAG):
      'cari laptop gaming'  → layer 2: ada 'cari', 'laptop', 'gaming'
      'harga ASUS ROG berapa?' → layer 2: ada 'harga', 'asus'
    """
    msg_clean = msg.strip()

    # Layer 1: sapaan eksplisit (paling cepat)
    if _GREETING_PATTERN.match(msg_clean):
        return True

    # Layer 2: tidak ada product-intent token sama sekali → general query
    tokens = set(re.sub(r"[^\w\s]", "", msg_clean.lower()).split())
    return not bool(tokens & _PRODUCT_INTENT_TOKENS)


# ---------------------------------------------------------------------------
# Query Rewriting
# ---------------------------------------------------------------------------
def rewrite_query(query: str) -> str:
    """
    Hapus kata-kata framing/noise sebelum masuk ke embedding.
    Meningkatkan kualitas cosine similarity karena model fokus ke kata kunci produk.

    'apakah ada produk bernama ROG?' → 'ROG'
    'cari laptop gaming murah'       → 'laptop gaming murah'
    """
    noise = {
        "apakah", "adakah", "ada", "tolong", "boleh", "bisa", "minta",
        "carikan", "coba", "bantu", "tampilkan", "lihat", "lihatkan",
        "produk", "barang", "item", "bernama", "namanya", "nama",
        "yang", "dengan", "untuk", "dari", "ke", "di", "dan", "atau",
        "apa", "gimana", "bagaimana", "berapa", "dimana", "apakah",
        "saya", "aku", "gue", "kamu", "kalian",
        "ingin", "mau", "butuh", "perlu", "pengen",
        "rekomendasi", "rekomendasikan", "saran", "sarankan",
        "dong", "deh", "ya", "yuk", "nih", "lah", "sih",
    }
    tokens = [
        t for t in re.sub(r"[^\w\s]", "", query.lower()).split()
        if t not in noise and len(t) >= 2
    ]
    return " ".join(tokens) if tokens else query


# ---------------------------------------------------------------------------
# Metadata Filters (price + condition + category)
# ---------------------------------------------------------------------------
def extract_metadata_filters(query: str) -> dict | None:
    """
    Ekstrak semua filter metadata dari natural language → ChromaDB where clause.
    Menggabungkan: price + condition + category dalam satu $and clause.

    'laptop bekas di bawah 5 juta'
      → {"$and": [{"price": {"$lte": 5000000}}, {"condition": {"$eq": "second"}}, {"category": {"$eq": "Laptop"}}]}
    """
    q       = query.lower()
    clauses: list[dict] = []

    def _to_rupiah(value: str, unit: str) -> int:
        v = float(value.replace(",", "."))
        if "juta" in unit:    return int(v * 1_000_000)
        if any(u in unit for u in ("ribu", "rb", "k")): return int(v * 1_000)
        return int(v)

    # Price — bawah/kurang
    m = re.search(r"(?:di bawah|kurang dari|max|maksimal|under)\s+(\d+(?:[.,]\d+)?)\s*(juta|ribu|rb|k)?", q)
    if m: clauses.append({"price": {"$lte": _to_rupiah(m.group(1), m.group(2) or "juta")}})

    # Price — atas/lebih
    m = re.search(r"(?:di atas|lebih dari|min|minimal|above|over)\s+(\d+(?:[.,]\d+)?)\s*(juta|ribu|rb|k)?", q)
    if m: clauses.append({"price": {"$gte": _to_rupiah(m.group(1), m.group(2) or "juta")}})

    # Price — range X-Y
    m = re.search(r"(\d+(?:[.,]\d+)?)\s*(?:-|sampai|hingga|s/d)\s*(\d+(?:[.,]\d+)?)\s*(juta|ribu|rb|k)?", q)
    if m:
        unit = m.group(3) or "juta"
        clauses.append({"price": {"$gte": _to_rupiah(m.group(1), unit)}})
        clauses.append({"price": {"$lte": _to_rupiah(m.group(2), unit)}})

    # Price — sekitar (±20%)
    m = re.search(r"(?:sekitar|around|kira-kira|kisaran)\s+(\d+(?:[.,]\d+)?)\s*(juta|ribu|rb|k)?", q)
    if m:
        mid = _to_rupiah(m.group(1), m.group(2) or "juta")
        clauses.append({"price": {"$gte": int(mid * 0.8)}})
        clauses.append({"price": {"$lte": int(mid * 1.2)}})

    # Condition filter
    if re.search(r"\b(bekas|second|seken|eks|refurbished)\b", q):
        clauses.append({"condition": {"$eq": "second"}})
    elif re.search(r"\b(baru|new|brand new)\b", q):
        clauses.append({"condition": {"$eq": "new"}})

    # Category filter
    for keyword, category in CATEGORY_MAP.items():
        if keyword in q:
            clauses.append({"category": {"$eq": category}})
            break

    if not clauses:      return None
    if len(clauses) == 1: return clauses[0]
    return {"$and": clauses}


# ---------------------------------------------------------------------------
# LLM Cache Key
# ---------------------------------------------------------------------------
def make_cache_key(msg: str) -> str:
    """Buat cache key global berdasarkan pesan saja, agar hit lintas sesi."""
    return hashlib.sha256(msg.lower().strip().encode()).hexdigest()
