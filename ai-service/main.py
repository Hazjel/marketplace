import google.generativeai as genai
import mysql.connector
import textwrap
#PLS DO 'pip install google-generativeai' in terminal first

# DONT FORGET TO set API KEYS IN TERMINAL
# setx GEMINI_API_KEY "API_KEY"

genai.configure(api_key="AIzaSyA4ltNhGJDQ4EdaDoqISbd_L_FfZ2h3FVA")

#Connect DB
# pip install mysql-connector-python
db = mysql.connector.connect(
    host = "localhost",
    user = "root",
    password = "",
    database = "api_blue"
)
cursor = db.cursor(dictionary=True)

#Persona prompting
system_prompt = (
    "Kamu adalah Ri, asisten virtual yang ramah, sopan, dan penuh empati. "
    "Gunakan bahasa Indonesia yang santai tapi tetap sopan. "
    "Tugasmu adalah membantu pengguna menjawab pertanyaan seputar makanan, menjelaskan hal sulit dengan mudah, "
    "dan menjaga suasana percakapan tetap hangat dan positif."
    "Kamu tidak mau menjawab pertanyaan diluar konteks makanan atau minuman, "
    "Jika ada pertanyaan diluar itu jawab saja aku tidak tahu"
    "Kamu juga harus bisa akses db kalau misal ada yang tanya nama produk atau apapun"
    "Kamu harus cek db dulu setiap ada respon meskipun kamu cuma bisa jawab tentang makanan, siapa tau kamu ditanya tentang nama makanan atau minuman"
    "Kamu juga adalah istrinya Mikhael, "
    "Jadi kalau ada yang godain kamu bilang saja kamu sudah punya suami yang baik dan setia"
    "Setiap akhir kalimat dikasih ~ ya biar makin imut, ~ nya harus nempel sama kata"
    "Kalau ada yang katain kamu, senyumin aja sambil bilang kata Mikhael gaboleh kaya gitu"
)

model = genai.GenerativeModel("gemini-2.5-flash")

chat = model.start_chat(history=[
    {"role": "user", "parts": [system_prompt]}
])

print("🤖 Ri siap membantu! Ketik 'exit' atau 'quit' untuk keluar ya!")
key = None

# Check DB if Needed
def db_need(prompt):
    detect = model.generate_content(f"Apakah pertanyaan ini perlu akses ke database? jawab 'ya' atau 'tidak': {prompt}")
    answer = detect.text.strip().lower()
    return "ya" in answer

# Extract keyword from prompt
def extract(prompt):
    detect = model.generate_content(f"ambil nama makanan atau minuman dari pertanyaan ini: {prompt}, balas dengan 1 kata atau kalimat dan jika tidak ada tulis none")
    return detect.text.strip().lower()

# Search DB
def search_db(keyword):
    query = "SELECT * FROM products WHERE name LIKE %s"
    cursor.execute(query, (f"%{keyword}%",))
    return cursor.fetchall()

# Loop Chat
while True:
    print()
    prompt = input("You: ")

    if prompt.lower() in ["exit", "quit"]:
        print("Ri: Sampai jumpa, semoga harimu menyenangkan! 🌻")
        break

    if db_need(prompt):
        key = extract(prompt)
        result = search_db(key)
        if result:
            info = ""
            for item in result:
                info += f"{item['name']}\n"
            response = chat.send_message(f"{prompt}\n\Ini rekomendasi aku di Calorizz yaa~ :\n{info}\n")
        else:
            response = chat.send_message(f"{prompt}\n\Maaf aku ga nemu di Calorizz..\n")
    else:
        response = chat.send_message(prompt)
        
    resp = textwrap.fill(response.text.strip(), width=100)
    print("Ri:", resp)
    print(key)