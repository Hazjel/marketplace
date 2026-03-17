import os
import asyncio
import re
import time
import httpx
import mysql.connector
from fastapi import FastAPI, Response
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from dotenv import load_dotenv
from prometheus_client import Counter, Histogram, generate_latest, CONTENT_TYPE_LATEST

# Instalasi
# pip install fastapi uvicorn
# pip install mysql-connect-python
# pip install python-dotenv
# pip install httpx

# Load Environment Variables
load_dotenv(override=True)

# Konfigurasi Ollama (Local)
OLLAMA_BASE_URL = os.getenv("OLLAMA_BASE_URL", "http://localhost:11434").rstrip("/")
OLLAMA_MODEL = os.getenv("OLLAMA_MODEL", "qwen3:8b")
OLLAMA_TIMEOUT_S = float(os.getenv("OLLAMA_TIMEOUT_S", "180"))
OLLAMA_MAX_RETRIES = int(os.getenv("OLLAMA_MAX_RETRIES", "1"))

# Inisialisasi FastAPI
app = FastAPI()

app.add_middleware(
    CORSMiddleware, 
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Metrics (Prometheus)
REQUEST_COUNT = Counter(
    "http_requests_total",
    "Total number of HTTP requests",
    ["method", "endpoint", "status"],
)
REQUEST_LATENCY = Histogram(
    "http_request_duration_seconds",
    "HTTP request latency in seconds",
    ["endpoint"],
)

@app.middleware("http")
async def metrics_middleware(request, call_next):
    start_time = time.perf_counter()
    response = await call_next(request)
    elapsed = time.perf_counter() - start_time
    endpoint = request.url.path
    REQUEST_LATENCY.labels(endpoint=endpoint).observe(elapsed)
    REQUEST_COUNT.labels(
        method=request.method,
        endpoint=endpoint,
        status=str(response.status_code),
    ).inc()
    return response

# Model Data Request (Dari Laravel/Vue)
class ChatRequest(BaseModel):
    message: str

# SYSTEM PROMPT PERSONA RI 
SYSTEM_PROMPT = (
    "Kamu adalah Ri, asisten virtual Bluee yang ramah, sopan, dan penuh empati. "
    "Bluee adalah marketplace yang menjual produk-produk berkualitas dan dengan harga terbaik. "
    "Gunakan bahasa Indonesia yang santai tapi tetap sopan. "
    "Tugasmu adalah membantu pengguna menjawab pertanyaan seputar produk dan barang, menjelaskan hal sulit dengan mudah, "
    "dan menjaga suasana percakapan tetap hangat dan positif. "
    "Kamu tidak mau menjawab pertanyaan diluar konteks produk yang dijual atau kebijakan di marketplace ini. "
    #"Jika produk ditemukan di database, jawab dan jelaskan saja seolah-olah itu adalah makanan walaupun itu bukan makanan atau minuman"
    "Jika ada pertanyaan diluar itu jawab saja 'Maaf ya, Ri cuma tau soal makanan dan minuman di sini~'. "
    "Kamu juga adalah istrinya Mikhael. "
    "Jadi kalau ada yang godain kamu bilang saja kamu sudah punya suami yang baik dan setia. "
    "Setiap akhir kalimat dikasih ~ ya biar makin imut, ~ nya harus nempel sama kata. "
    "Kalau ada yang katain kamu, senyumin aja sambil bilang 'Kata Mikhael gaboleh kasar gitu~'. "
    "Jawablah berdasarkan informasi data produk yang diberikan jika ada."
    "Tolong berikan respon serapi mungkin, jika perlu buatlah baris baru"
)

# FUNGSI DATABASE 
def get_db_connection():
    try:
        return mysql.connector.connect(
            host=os.getenv("DB_HOST"),
            user=os.getenv("DB_USER"),
            password=os.getenv("DB_PASSWORD"),
            database=os.getenv("DB_NAME")
        )
    except mysql.connector.Error as err:
        print(f"Error Database: {err}")
        return None

def search_db(keyword):
    conn = get_db_connection()
    if not conn:
        return []
    
    try:
        cursor = conn.cursor(dictionary=True)
        # Cari produk berdasarkan nama
        query = "SELECT name, price, description FROM products WHERE name LIKE %s LIMIT 5"
        cursor.execute(query, (f"%{keyword}%",))
        results = cursor.fetchall()
        return results
    except Exception as e:
        print(f"Error Query: {e}")
        return []
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()

# EXTRACT KEYWORD
def _parse_classifier_output(raw_text: str):
    if not raw_text:
        return False, "none"

    line = raw_text.strip().splitlines()[0]
    # Try strict format: YA|keyword
    if "|" in line:
        left, right = line.split("|", 1)
        decision = left.strip().upper()
        keyword = right.strip()
    else:
        # Allow: YA: keyword or YA - keyword
        match = re.match(r"^\s*(YA|TIDAK)\s*[:\-]\s*(.+)\s*$", line, re.IGNORECASE)
        if match:
            decision = match.group(1).upper()
            keyword = match.group(2).strip()
        else:
            decision = "YA" if re.search(r"\bYA\b", line, re.IGNORECASE) and not re.search(r"\bTIDAK\b", line, re.IGNORECASE) else "TIDAK"
            keyword = "none"

    if decision == "YA" and keyword and keyword.lower() != "none":
        return True, keyword.lower()
    return False, "none"


async def _ollama_chat(messages, temperature: float | None = None) -> str:
    payload = {
        "model": OLLAMA_MODEL,
        "messages": messages,
        "stream": False,
    }
    if temperature is not None:
        payload["options"] = {"temperature": temperature}

    last_error = None
    for attempt in range(OLLAMA_MAX_RETRIES + 1):
        try:
            timeout = httpx.Timeout(OLLAMA_TIMEOUT_S)
            async with httpx.AsyncClient(timeout=timeout) as client:
                response = await client.post(f"{OLLAMA_BASE_URL}/api/chat", json=payload)

            if response.status_code >= 400:
                try:
                    data = response.json()
                    error_msg = data.get("error", response.text)
                except Exception:
                    error_msg = response.text
                raise RuntimeError(f"Ollama error {response.status_code}: {error_msg}")

            data = response.json()
            content = data.get("message", {}).get("content")
            if not content:
                raise RuntimeError("Ollama response missing content")
            return content
        except (httpx.TimeoutException, httpx.NetworkError, RuntimeError) as err:
            last_error = err
            if attempt < OLLAMA_MAX_RETRIES:
                await asyncio.sleep(0.5 * (attempt + 1))
                continue
            raise

    raise last_error or RuntimeError("Unknown Ollama error")


async def analyze_prompt_and_extract_key(user_message):
    """Menggabungkan check_db_needed dan extract_keyword menjadi 1 panggilan API."""
    
    instruction = (
        "Analisis pertanyaan pengguna. Jawablah YA jika pengguna MENYEBUTKAN NAMA PRODUK atau bertanya tentang KETERSEDIAAN. Jawab TIDAK jika itu pertanyaan umum atau non-makanan. "
        "Jika YA, ekstrak 1 kata kunci nama produk/makanan yang disebutkan. "
        "Jika TIDAK, keyword-nya harus 'none'. "
        "Balas HANYA dengan format: YA/TIDAK|kata kunci. Contoh: YA|Burger, atau: TIDAK|none."
        
        # Contoh prompting (Few-Shot Prompting)
        "\nCONTOH 1: User bertanya: 'Apakah ada minuman dingin?'. Balas: TIDAK|none"
        "\nCONTOH 2: User bertanya: 'Apakah kalian jual ayam goreng?'. Balas: YA|ayam goreng"
        "\nCONTOH 3: User bertanya: 'harga pizza gimana?'. Balas: YA|pizza"
        "\nCONTOH 4: User bertanya: 'apakah disini ada fuga veniam'. Balas: YA|fuga veniam"
    )
    
    try:
        # PANGGILAN MODEL LOKAL: Analisis dan Ekstraksi Kata Kunci
        response_text = await _ollama_chat(
            [
                {"role": "system", "content": instruction},
                {"role": "user", "content": "Pertanyaan: " + user_message},
            ],
            temperature=0.0,
        )
        return _parse_classifier_output(response_text)
    except Exception as e:
        print(f"Error Analisis Model Lokal: {e}")
        return False, "none"

# STATUS HELPER
def _status_from_flags(quota_exceeded: bool, had_error: bool) -> str:
    if quota_exceeded:
        return "quota_exceeded"
    if had_error:
        return "error"
    return "success"

# ENDPOINT UTAMA
@app.post("/predict")
async def predict_response(request: ChatRequest):
    user_msg = request.message
    context_info = ""
    reply_text = ""
    quota_exceeded = False
    had_error = False

    try:
        # ANALISIS (PANGGILAN MODEL LOKAL 1)
        _, keyword = await analyze_prompt_and_extract_key(user_msg)
        print(f"DEBUG: Keyword={keyword}")
        
        # Proses DB 
        if keyword != "none":
            products = search_db(keyword)
            
            if products:
                product_list = "\n".join([f"- {p['name']} (Rp {p['price']}): Deskripsi: {p['description']}" for p in products])
                context_info = (
                    f"\n[SISTEM: User mencari produk. Berikut data yang ditemukan di database Calorizz:\n"
                    f"{product_list}\n"
                    f"Gunakan data ini untuk menjawab user dengan gaya Ri. Berikan saran terbaik dari daftar ini!]"
                )
            else:
                context_info = "\n[SISTEM: User mencari produk, tapi tidak ditemukan di database. Beritahu user dengan sopan bahwa produk tidak ada~]"
                
        # 3. KIRIM PESAN (PANGGILAN MODEL LOKAL 2)
        final_prompt = f"{user_msg} {context_info}".strip()
        reply_text = await _ollama_chat(
            [
                {"role": "system", "content": SYSTEM_PROMPT},
                {"role": "user", "content": final_prompt},
            ],
            temperature=0.7,
        )
        
    except Exception as e:
        # Penanganan error lain
        reply_text = "Duh, Ri lagi pusing nih karena ada error di server, coba tanya lagi nanti ya~"
        print(f"Error Umum di Predict: {e}")
        had_error = True

    return {
        "reply": reply_text,
        "status": _status_from_flags(quota_exceeded, had_error)
    }

@app.get("/metrics")
def metrics():
    return Response(content=generate_latest(), media_type=CONTENT_TYPE_LATEST)

# How to use: uvicorn main:app --reload --port 8001
