import re

# ---------------------------------------------------------------------------
# Deteksi pola kode/syntax pemrograman dalam output LLM.
# Lapis kedua di luar SYSTEM_PROMPT — jaga-jaga kalau model tetap
# kebobolan menulis kode meski sudah diinstruksikan menolak.
# ---------------------------------------------------------------------------
_CODE_PATTERNS = [
    r"```",                                   # markdown code fence
    r"\bdef\s+\w+\s*\(",                       # python function def
    r"\bfunc(tion)?\s*\w*\s*\([^)]*\)\s*[{:]", # func()/function(){}
    r"\bprint\s*\(",                           # print(...)
    r"\bconsole\.log\s*\(",                    # console.log(...)
    r"\b(import|from)\s+\w+\s+(import\s+)?\w*", # python/js import
    r"<\?php",                                 # php open tag
    r"\bSELECT\s+.+\s+FROM\s+",                # SQL
    r"^\s*(if|for|while)\s*\([^)]*\)\s*[{:]",  # control flow blocks
    r";\s*$",                                  # baris diakhiri semicolon (multi-line check)
]
_CODE_RE = re.compile("|".join(_CODE_PATTERNS), re.IGNORECASE | re.MULTILINE)

REFUSAL_MESSAGE = (
    "Maaf, Ri cuma bisa bantu soal belanja di Blukios ya~ "
    "Ada produk yang lagi kamu cari? Kalau butuh bantuan lebih detail, "
    "kamu juga bisa chat langsung ke toko lewat halaman produknya 😊"
)


def contains_code(text: str) -> bool:
    """True kalau teks mengandung pola kode/syntax pemrograman."""
    return bool(_CODE_RE.search(text))


def sanitize_reply(text: str) -> str:
    """Ganti balasan dengan penolakan standar kalau terdeteksi mengandung kode."""
    if contains_code(text):
        return REFUSAL_MESSAGE
    return text
