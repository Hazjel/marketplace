import os
import google.generativeai as genai
import mysql.connector
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from dotenv import load_dotenv

# 1. Load Environment Variables
load_dotenv()

# 2. Konfigurasi Gemini
genai.configure(api_key=os.getenv("GEMINI_API_KEY"))
model = genai.GenerativeModel("gemini-2.5-flash") # Atau gemini-pro jika 2.5 belum available di akunmu

# 3. Inisialisasi FastAPI
app = FastAPI()

# 4. Model Data Request (Dari Laravel/Vue)
class ChatRequest(BaseModel):
    message: str

# --- SYSTEM PROMPT PERSONA RI ---
SYSTEM_PROMPT = (
    "Kamu adalah Ri, asisten virtual yang ramah, sopan, dan penuh empati. "
    "Gunakan bahasa Indonesia yang santai tapi tetap sopan. "
    "Tugasmu adalah membantu pengguna menjawab pertanyaan seputar makanan, menjelaskan hal sulit dengan mudah, "
    "dan menjaga suasana percakapan tetap hangat dan positif. "
    "Kamu tidak mau menjawab pertanyaan diluar konteks makanan, minuman, atau produk yang dijual di marketplace ini. "
    "Jika ada pertanyaan diluar itu jawab saja 'Maaf ya, Ri cuma tau soal makanan dan minuman di sini~'. "
    "Kamu juga adalah istrinya Mikhael. "
    "Jadi kalau ada yang godain kamu bilang saja kamu sudah punya suami yang baik dan setia. "
    "Setiap akhir kalimat dikasih ~ ya biar makin imut, ~ nya harus nempel sama kata. "
    "Kalau ada yang katain kamu, senyumin aja sambil bilang 'Kata Mikhael gaboleh kasar gitu~'. "
    "Jawablah berdasarkan informasi data produk yang diberikan jika ada."
)

# --- FUNGSI DATABASE ---
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

# --- FUNGSI BANTUAN AI ---
def check_db_needed(user_message):
    """Cek apakah perlu akses DB"""
    prompt = f"Apakah pertanyaan ini mencari informasi spesifik tentang stok, harga, atau ketersediaan makanan/minuman yang mungkin ada di database toko? Jawab hanya 'YA' atau 'TIDAK'. Pertanyaan: {user_message}"
    response = model.generate_content(prompt)
    return "YA" in response.text.upper()

def extract_keyword(user_message):
    """Ambil kata kunci produk"""
    prompt = f"Ambil nama makanan atau minuman utama dari kalimat ini: '{user_message}'. Balas HANYA dengan 1 kata kunci atau nama produknya saja. Jika tidak ada, balas 'NONE'."
    response = model.generate_content(prompt)
    return response.text.strip().lower()

# --- ENDPOINT UTAMA ---
@app.post("/predict")
async def predict_response(request: ChatRequest):
    user_msg = request.message
    context_info = ""

    # 1. Cek apakah perlu akses DB
    if check_db_needed(user_msg):
        keyword = extract_keyword(user_msg)
        print(f"Mencari DB dengan keyword: {keyword}") # Debugging di terminal server
        
        if keyword != "none":
            products = search_db(keyword)
            if products:
                # Format data produk menjadi string agar bisa dibaca "Ri"
                product_list = "\n".join([f"- {p['name']} (Rp {p['price']}): {p['description']}" for p in products])
                context_info = (
                    f"\n[SISTEM: User bertanya tentang produk. Berikut data yang ditemukan di database Calorizz:\n"
                    f"{product_list}\n"
                    f"Gunakan data ini untuk menjawab user dengan gaya Ri.]"
                )
            else:
                context_info = "\n[SISTEM: User mencari produk, tapi tidak ditemukan di database. Beritahu user dengan sopan bahwa produk tidak ada~]"

    # 2. Mulai Chat dengan Persona + Context
    # Kita buat chat session baru tiap request agar stateless (atau kirim history dari frontend jika mau advanced)
    chat = model.start_chat(history=[
        {"role": "user", "parts": [SYSTEM_PROMPT]}
    ])

    # 3. Kirim pesan gabungan (Prompt User + Info Database)
    final_prompt = f"{user_msg} {context_info}"
    
    try:
        response = chat.send_message(final_prompt)
        reply_text = response.text.strip()
    except Exception as e:
        reply_text = "Duh, Ri lagi pusing nih, coba tanya lagi nanti ya~"
        print(f"Error Gemini: {e}")

    return {
        "reply": reply_text,
        "status": "success"
    }

# How to use: uvicorn main:app --reload --port 8001