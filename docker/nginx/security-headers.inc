# Header keamanan untuk yang diserve nginx sendiri (SPA, /storage, robots.txt).
# Respons /api dkk lewat PHP-FPM dan sudah dapat header yang sama dari middleware
# SecurityHeaders Laravel -- jangan di-include di location itu, nanti dobel.
# add_header di sebuah location MENGGANTI semua yang diwarisi, jadi file ini
# harus di-include di tiap location yang memakai add_header sendiri.
# Nilai sama dengan API (HSTS tanpa preload). geolocation=(self): MapPicker.vue
# dan AllStores.vue memanggil navigator.geolocation. Tidak ada getUserMedia di FE.
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=(self)" always;
