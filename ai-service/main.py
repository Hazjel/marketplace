import os
import google.generativeai as genai
from google.api_core import exceptions as google_exceptions
import mysql.connector
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from dotenv import load_dotenv

# Instalasi
# pip install google-generativeai
# pip install fastapi uvicorn
# pip install mysql-connect-python
# pip install python-dotenv

# Load Environment Variables
load_dotenv(override=True)

# Konfigurasi Gemini
API_GEMINI = os.getenv("GEMINI_API_KEY")
genai.configure(api_key=API_GEMINI)
model = genai.GenerativeModel("gemini-2.5-flash") # Gemini model

# Inisialisasi FastAPI
app = FastAPI()

origins = [
    "http://127.0.0.1",
    "http://localhost",
    "*"
]

app.add_middleware(
    CORSMiddleware, 
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

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
def analyze_prompt_and_extract_key(user_message):
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
        # PANGGILAN API 1: Analisis dan Ekstraksi Kata Kunci
        response = model.generate_content(
            instruction + "\n\nPertanyaan: " + user_message
        )
        
        raw_text = response.text.strip().upper()
        
        if '|' in raw_text:
            parts = raw_text.split('|', 1)
            is_food = parts[0].strip() == 'YA'
            keyword = parts[1].strip().lower()
            return is_food, keyword
        else:
            return False, 'none'
            
    except google_exceptions.ResourceExhausted as e:
        # Jika pemanggilan API menghabiskan kuota
        raise e
    except Exception as e:
        print(f"Error Analisis API: {e}")
        return False, 'none'

# ENDPOINT UTAMA
@app.post("/predict")
async def predict_response(request: ChatRequest):
    user_msg = request.message
    context_info = ""
    reply_text = ""

    try:
        # ANALISIS (PANGGILAN API 1)
        is_db_needed, keyword = analyze_prompt_and_extract_key(user_msg) 
        print(f"DEBUG: DB Needed={is_db_needed}, Keyword={keyword}")
        
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
                
        # 3. KIRIM PESAN (PANGGILAN API 2)
        chat = model.start_chat(history=[
            {"role": "user", "parts": [SYSTEM_PROMPT]}
        ])

        final_prompt = f"{user_msg} {context_info}"
        
        response = chat.send_message(final_prompt)
        reply_text = response.text.strip()
        
    except google_exceptions.ResourceExhausted:
        # PENANGANAN ERROR QUOTA HABIS
        print("ERROR: Kuota Gemini Habis (ResourceExhausted 429)")
        print(f"🔑 SERVER MEMBACA KEY: {API_GEMINI}")
        reply_text = "Maaf banget, Ri harus istirahat nih~ Coba tunggu sebentar (sekitar 1 menit) lalu coba lagi ya~ Janji deh, Ri akan bantu lagi~"
        
    except Exception as e:
        # Penanganan error lain
        reply_text = "Duh, Ri lagi pusing nih karena ada error di server, coba tanya lagi nanti ya~"
        print(f"Error Umum di Predict: {e}")

    return {
        "reply": reply_text,
        "status": "success" if "Maaf banget" not in reply_text else "quota_exceeded"
    }

# How to use: uvicorn main:app --reload --port 8001