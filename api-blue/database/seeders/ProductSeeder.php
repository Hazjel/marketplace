<?php

namespace Database\Seeders;

use App\Helpers\ImageHelper\ImageHelper;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $imageHelper = new ImageHelper;
        $stores = Store::all();

        if ($stores->isEmpty()) {
            $this->command->warn('No stores found. Skipping ProductSeeder.');

            return;
        }

        $products = [

            // ─────────────────────────────────────────
            // SMARTPHONE
            // ─────────────────────────────────────────
            [
                'name' => 'iPhone 15 Pro Max 256GB Natural Titanium',
                'description' => 'iPhone 15 Pro Max dengan chip A17 Pro, kamera 48MP dengan 5x optical zoom, layar Super Retina XDR 6.7 inci ProMotion 120Hz. Desain titanium premium, USB-C, Action Button yang dapat dikustomisasi.',
                'price' => 21999000,
                'weight' => 0.22,
                'stock' => 15,
                'condition' => 'new',
                'category' => 'Smartphone',
            ],
            [
                'name' => 'Samsung Galaxy S24 Ultra 512GB Titanium Black',
                'description' => 'Samsung Galaxy S24 Ultra dengan Snapdragon 8 Gen 3, S Pen built-in, kamera 200MP dengan AI zoom, layar Dynamic AMOLED 2X 6.8 inci QHD+ 120Hz. Galaxy AI untuk produktivitas.',
                'price' => 23499000,
                'weight' => 0.23,
                'stock' => 12,
                'condition' => 'new',
                'category' => 'Smartphone',
            ],
            [
                'name' => 'Xiaomi 14 Ultra 512GB Black',
                'description' => 'Xiaomi 14 Ultra dengan sistem kamera Leica, 4 kamera belakang dengan lensa variable aperture, Snapdragon 8 Gen 3, layar LTPO AMOLED 6.73 inci, fast charging 90W HyperCharge.',
                'price' => 14999000,
                'weight' => 0.22,
                'stock' => 10,
                'condition' => 'new',
                'category' => 'Smartphone',
            ],
            [
                'name' => 'OPPO Find X8 Pro 256GB Black',
                'description' => 'OPPO Find X8 Pro dengan Hasselblad Master System, dual periscope telephoto 3x & 6x, Dimensity 9300, layar AMOLED 6.78 inci 120Hz, baterai 5910mAh SuperVOOC 80W.',
                'price' => 15999000,
                'weight' => 0.22,
                'stock' => 8,
                'condition' => 'new',
                'category' => 'Smartphone',
            ],
            [
                'name' => 'Samsung Galaxy A55 5G 256GB Awesome Navy',
                'description' => 'Samsung Galaxy A55 5G dengan Exynos 1480, RAM 8GB, kamera 50MP OIS, layar Super AMOLED 6.6 inci 120Hz. Galaxy AI, IP67 dust & water resistant.',
                'price' => 5999000,
                'weight' => 0.21,
                'stock' => 40,
                'condition' => 'new',
                'category' => 'Smartphone',
            ],
            [
                'name' => 'Xiaomi Redmi Note 13 Pro 5G 256GB',
                'description' => 'Redmi Note 13 Pro 5G dengan Snapdragon 7s Gen 2, kamera 200MP OIS, layar AMOLED 6.67 inci 120Hz, fast charging 67W. Baterai 5100mAh, IP54.',
                'price' => 4299000,
                'weight' => 0.20,
                'stock' => 50,
                'condition' => 'new',
                'category' => 'Smartphone',
            ],
            [
                'name' => 'POCO X6 Pro 5G 256GB Yellow',
                'description' => 'POCO X6 Pro 5G dengan Dimensity 8300-Ultra, layar Flow AMOLED 6.67 inci 144Hz, kamera 64MP OIS, fast charging 67W, baterai 5000mAh. Performa gaming terbaik di kelasnya.',
                'price' => 4799000,
                'weight' => 0.19,
                'stock' => 35,
                'condition' => 'new',
                'category' => 'Smartphone',
            ],
            [
                'name' => 'Realme GT 6 256GB Fluid Silver',
                'description' => 'Realme GT 6 dengan Snapdragon 8s Gen 3, layar AMOLED 6.78 inci 120Hz, kamera 50MP Sony LYT-808, fast charging 120W, baterai 5500mAh. Daya tahan lama untuk gaming.',
                'price' => 7499000,
                'weight' => 0.20,
                'stock' => 25,
                'condition' => 'new',
                'category' => 'Smartphone',
            ],
            [
                'name' => 'Vivo V30 5G 256GB Peacock Green',
                'description' => 'Vivo V30 5G dengan ZEISS Portrait Camera, kamera depan 50MP, Snapdragon 7 Gen 3, layar AMOLED 6.78 inci 120Hz, fast charging 80W. Fokus di fotografi potret.',
                'price' => 5499000,
                'weight' => 0.19,
                'stock' => 30,
                'condition' => 'new',
                'category' => 'Smartphone',
            ],
            [
                'name' => 'Infinix Hot 40i 128GB',
                'description' => 'Infinix Hot 40i dengan Helio G85, RAM 8GB + 8GB extended, kamera 48MP AI, layar 6.56 inci 90Hz IPS, baterai 5000mAh. HP entry-level terbaik untuk aktivitas harian.',
                'price' => 1499000,
                'weight' => 0.20,
                'stock' => 100,
                'condition' => 'new',
                'category' => 'Smartphone',
            ],
            [
                'name' => 'Nothing Phone (2a) 256GB Black',
                'description' => 'Nothing Phone (2a) dengan Dimensity 7200 Pro, Glyph Interface unik, layar AMOLED 6.7 inci 120Hz, kamera 50MP OIS, fast charging 45W, baterai 5000mAh. Desain transparan ikonik.',
                'price' => 5299000,
                'weight' => 0.19,
                'stock' => 20,
                'condition' => 'new',
                'category' => 'Smartphone',
            ],

            // ─────────────────────────────────────────
            // LAPTOP
            // ─────────────────────────────────────────
            [
                'name' => 'MacBook Pro 14" M3 Pro 18GB/512GB Silver',
                'description' => 'MacBook Pro 14 inci dengan chip M3 Pro, 18GB unified memory, SSD 512GB. Liquid Retina XDR 3024×1964 ProMotion 120Hz. 17 jam battery life, MagSafe 3, Thunderbolt 4.',
                'price' => 29999000,
                'weight' => 1.60,
                'stock' => 8,
                'condition' => 'new',
                'category' => 'Laptop',
            ],
            [
                'name' => 'MacBook Air 15" M3 8GB/256GB Starlight',
                'description' => 'MacBook Air 15 inci dengan chip M3, 8GB unified memory, SSD 256GB. Layar Liquid Retina 15.3 inci. 18 jam battery life, desain tipis tanpa kipas.',
                'price' => 19999000,
                'weight' => 1.51,
                'stock' => 12,
                'condition' => 'new',
                'category' => 'Laptop',
            ],
            [
                'name' => 'ASUS ROG Strix G16 RTX 4070 2024',
                'description' => 'Laptop gaming ASUS ROG Strix G16 dengan Intel Core i9-14900HX, NVIDIA RTX 4070 8GB, RAM 32GB DDR5, SSD 1TB. Layar QHD 240Hz, ROG Nebula Display, RGB Aura Sync.',
                'price' => 29999000,
                'weight' => 2.50,
                'stock' => 5,
                'condition' => 'new',
                'category' => 'Laptop',
            ],
            [
                'name' => 'ASUS TUF Gaming A15 RTX 4060 FA507',
                'description' => 'Laptop gaming tangguh ASUS TUF A15 dengan AMD Ryzen 9 7945HX, NVIDIA RTX 4060 8GB, RAM 16GB DDR5, SSD 512GB. Layar FHD 144Hz, MIL-STD-810H certified.',
                'price' => 16999000,
                'weight' => 2.20,
                'stock' => 10,
                'condition' => 'new',
                'category' => 'Laptop',
            ],
            [
                'name' => 'Lenovo ThinkPad X1 Carbon Gen 12',
                'description' => 'Ultrabook bisnis premium ThinkPad X1 Carbon dengan Intel Core Ultra 7 165U, RAM 16GB LPDDR5x, SSD 512GB. Layar 14" 2.8K OLED, berat 1.09kg, ThinkShield security.',
                'price' => 26999000,
                'weight' => 1.09,
                'stock' => 5,
                'condition' => 'new',
                'category' => 'Laptop',
            ],
            [
                'name' => 'Lenovo IdeaPad Slim 5 14" OLED Ryzen 5',
                'description' => 'Lenovo IdeaPad Slim 5 dengan AMD Ryzen 5 7530U, RAM 16GB, SSD 512GB, layar OLED 14" 2.8K 90Hz. Ringan 1.46kg, desain elegan untuk mahasiswa dan profesional.',
                'price' => 10999000,
                'weight' => 1.46,
                'stock' => 20,
                'condition' => 'new',
                'category' => 'Laptop',
            ],
            [
                'name' => 'Acer Nitro V 15 RTX 4050 ANV15-51',
                'description' => 'Laptop gaming Acer Nitro V dengan Intel Core i5-13420H, NVIDIA RTX 4050 6GB, RAM 16GB DDR5, SSD 512GB. Layar FHD 144Hz IPS, DualFan pendingin efisien.',
                'price' => 12499000,
                'weight' => 2.10,
                'stock' => 15,
                'condition' => 'new',
                'category' => 'Laptop',
            ],
            [
                'name' => 'HP Pavilion 14 Core i5-1335U 16GB',
                'description' => 'HP Pavilion 14 dengan Intel Core i5-1335U, RAM 16GB DDR4, SSD 512GB. Layar FHD IPS 14 inci. Ringan 1.41kg, cocok untuk kuliah dan kerja kantoran sehari-hari.',
                'price' => 9499000,
                'weight' => 1.41,
                'stock' => 25,
                'condition' => 'new',
                'category' => 'Laptop',
            ],
            [
                'name' => 'MSI Prestige 14 AI Evo C1MG',
                'description' => 'MSI Prestige 14 AI Evo dengan Intel Core Ultra 7 155H, Intel Arc Graphics, RAM 32GB, SSD 1TB. Sertifikat Intel EVO, AI-powered, berat 1.29kg untuk profesional mobile.',
                'price' => 18999000,
                'weight' => 1.29,
                'stock' => 7,
                'condition' => 'new',
                'category' => 'Laptop',
            ],
            [
                'name' => 'Dell Inspiron 15 3000 Ryzen 5 8GB',
                'description' => 'Dell Inspiron 15 3000 dengan AMD Ryzen 5 7520U, RAM 8GB, SSD 256GB. Layar FHD 15.6 inci. Laptop entry-level andalan Dell, cocok untuk kebutuhan dasar kuliah.',
                'price' => 7499000,
                'weight' => 1.70,
                'stock' => 30,
                'condition' => 'new',
                'category' => 'Laptop',
            ],

            // ─────────────────────────────────────────
            // AKSESORIS GADGET
            // ─────────────────────────────────────────
            [
                'name' => 'Apple AirPods Pro 2nd Gen USB-C',
                'description' => 'AirPods Pro generasi 2 dengan konektor USB-C, Active Noise Cancellation H2 chip, Adaptive Audio, Personalized Spatial Audio, case MagSafe. IPX4 water resistant.',
                'price' => 3799000,
                'weight' => 0.05,
                'stock' => 30,
                'condition' => 'new',
                'category' => 'Aksesoris gadget',
            ],
            [
                'name' => 'Samsung Galaxy Buds3 Pro White',
                'description' => 'Galaxy Buds3 Pro dengan desain blade-style baru, ANC canggih, driver 2-way, 360 Audio, konektivitas Bluetooth 5.4. 6 jam playback + 24 jam dengan case.',
                'price' => 3199000,
                'weight' => 0.05,
                'stock' => 25,
                'condition' => 'new',
                'category' => 'Aksesoris gadget',
            ],
            [
                'name' => 'Jabra Evolve2 55 ANC Wireless Headset',
                'description' => 'Headset wireless profesional Jabra Evolve2 55 dengan ANC, 8 mic array, 36 jam baterai, Bluetooth 5.2, kompatibel UC. Ideal untuk WFH dan video call.',
                'price' => 4299000,
                'weight' => 0.26,
                'stock' => 10,
                'condition' => 'new',
                'category' => 'Aksesoris gadget',
            ],
            [
                'name' => 'Anker 737 PowerCore 24K 140W',
                'description' => 'Power bank 24000mAh Anker 737 dengan output 140W USB-C, bisa charge laptop MacBook. Display layar digital, fast charge, berat 685g. Charge 3 device sekaligus.',
                'price' => 1099000,
                'weight' => 0.69,
                'stock' => 40,
                'condition' => 'new',
                'category' => 'Aksesoris gadget',
            ],
            [
                'name' => 'Baseus 65W GaN USB-C Charger',
                'description' => 'Charger GaN Baseus 65W dengan 2x USB-C + 1x USB-A. Kompatibel MacBook, laptop, HP. Compact, ukuran mirip charger HP standar, teknologi GaN generasi terbaru.',
                'price' => 299000,
                'weight' => 0.14,
                'stock' => 80,
                'condition' => 'new',
                'category' => 'Aksesoris gadget',
            ],
            [
                'name' => 'Logitech MX Master 3S Wireless Mouse Graphite',
                'description' => 'Mouse wireless premium MX Master 3S dengan sensor 8000 DPI, MagSpeed electromagnetic scroll wheel, 7 tombol, USB-C charging, 70 hari baterai. Multi-device 3 in 1.',
                'price' => 1499000,
                'weight' => 0.14,
                'stock' => 20,
                'condition' => 'new',
                'category' => 'Aksesoris gadget',
            ],
            [
                'name' => 'Keychron K8 Pro Mechanical Keyboard Wireless RGB',
                'description' => 'Mechanical keyboard tenkeyless wireless Keychron K8 Pro, hot-swappable Gateron G Pro switches, RGB backlight, kompatibel Mac & Windows, Bluetooth 5.1 + USB-C.',
                'price' => 1299000,
                'weight' => 0.95,
                'stock' => 18,
                'condition' => 'new',
                'category' => 'Aksesoris gadget',
            ],
            [
                'name' => 'Samsung Galaxy Watch6 Classic 47mm Black',
                'description' => 'Smartwatch premium dengan rotating bezel klasik, layar Super AMOLED 1.5 inci, BIA body composition sensor, GPS, auto workout detection, Wear OS. Water resistant 5ATM.',
                'price' => 5499000,
                'weight' => 0.06,
                'stock' => 12,
                'condition' => 'new',
                'category' => 'Aksesoris gadget',
            ],
            [
                'name' => 'Xiaomi Smart Band 8 Pro',
                'description' => 'Smartband Xiaomi 8 Pro dengan layar AMOLED 1.74 inci persegi, GPS built-in, monitoring SpO2 & detak jantung 24/7, 150+ mode olahraga, 14 hari baterai. Water resistant.',
                'price' => 699000,
                'weight' => 0.02,
                'stock' => 60,
                'condition' => 'new',
                'category' => 'Aksesoris gadget',
            ],
            [
                'name' => 'Ugreen Revodok Pro 210 13-in-1 USB-C Hub',
                'description' => 'USB-C Hub 13-in-1 Ugreen dengan 100W PD, 4K HDMI x2, 10Gbps USB-A, SD/TF card reader, Ethernet. Kompatibel MacBook, iPad Pro, laptop Windows.',
                'price' => 699000,
                'weight' => 0.19,
                'stock' => 35,
                'condition' => 'new',
                'category' => 'Aksesoris gadget',
            ],
            [
                'name' => 'Sandisk Extreme Pro 1TB Portable SSD',
                'description' => 'SSD portable Sandisk Extreme Pro 1TB dengan kecepatan baca 2000MB/s, USB 3.2 Gen 2x2, IP55 tahan debu & air, bodi aluminium premium. Backup foto dan video 4K.',
                'price' => 1599000,
                'weight' => 0.10,
                'stock' => 22,
                'condition' => 'new',
                'category' => 'Aksesoris gadget',
            ],

            // ─────────────────────────────────────────
            // PAKAIAN PRIA
            // ─────────────────────────────────────────
            [
                'name' => 'Kaos Polo Uniqlo Dry-EX Short Sleeve',
                'description' => 'Polo shirt Uniqlo Dry-EX berbahan polyester ringan dengan teknologi moisture-wicking. Tidak mudah kusut, cepat kering, cocok untuk kerja dan casual. Ukuran S-3XL.',
                'price' => 199000,
                'weight' => 0.20,
                'stock' => 150,
                'condition' => 'new',
                'category' => 'Pakaian pria',
            ],
            [
                'name' => 'Kemeja Oxford Button Down H&M Regular Fit',
                'description' => 'Kemeja Oxford H&M dengan bahan katun 100% berkualitas. Cocok untuk smart casual atau formal santai. Tersedia putih, biru muda, dan navy. Ukuran S-XXL.',
                'price' => 299000,
                'weight' => 0.28,
                'stock' => 80,
                'condition' => 'new',
                'category' => 'Pakaian pria',
            ],
            [
                'name' => 'Celana Chino Erigo Slim Fit Stretch Khaki',
                'description' => 'Celana chino slim fit Erigo dengan bahan cotton stretch 98%+2% spandex. Nyaman untuk aktivitas seharian, tidak mengekang gerak. Tersedia 8 warna. Ukuran 27-38.',
                'price' => 199000,
                'weight' => 0.40,
                'stock' => 100,
                'condition' => 'new',
                'category' => 'Pakaian pria',
            ],
            [
                'name' => 'Jaket Varsity Bomberindo Black',
                'description' => 'Jaket varsity bomber dengan bahan wool body dan kulit sintetis di sleeve. Desain klasik American college style, kancing snap, saku tangan dan dada. Ukuran S-XL.',
                'price' => 449000,
                'weight' => 0.70,
                'stock' => 30,
                'condition' => 'new',
                'category' => 'Pakaian pria',
            ],
            [
                'name' => "Celana Jeans Levi's 511 Slim 32x32",
                'description' => "Celana jeans Levi's 511 Slim Fit berbahan denim premium stretch. Desain slim dari pinggul ke kaki, nyaman dan stylish. Original Levi's Indonesia.",
                'price' => 799000,
                'weight' => 0.55,
                'stock' => 25,
                'condition' => 'new',
                'category' => 'Pakaian pria',
            ],
            [
                'name' => 'Kaos Oversize Cotton Combed 30s Putih',
                'description' => 'Kaos oversize bahan cotton combed 30s premium 180gsm. Adem, lembut, tidak pilling. Desain boxy clean untuk streetwear casual. Ukuran M-2XL.',
                'price' => 129000,
                'weight' => 0.22,
                'stock' => 200,
                'condition' => 'new',
                'category' => 'Pakaian pria',
            ],
            [
                'name' => 'Hoodie Fleece Uniqlo Sweat Full Zip Navy',
                'description' => 'Hoodie zip Uniqlo berbahan cotton fleece tebal hangat. Kualitas Uniqlo premium, tidak mudah pilling, jahitan rapi. Cocok untuk cuaca dingin. Ukuran XS-3XL.',
                'price' => 499000,
                'weight' => 0.60,
                'stock' => 45,
                'condition' => 'new',
                'category' => 'Pakaian pria',
            ],
            [
                'name' => 'Shorts Cargo Pria 5-Pocket Cotton',
                'description' => 'Celana pendek cargo pria berbahan cotton twill 240gsm. 6 saku fungsional, desain santai dan stylish. Cocok untuk outdoor dan casual. Ukuran 29-38.',
                'price' => 169000,
                'weight' => 0.35,
                'stock' => 75,
                'condition' => 'new',
                'category' => 'Pakaian pria',
            ],

            // ─────────────────────────────────────────
            // PAKAIAN WANITA
            // ─────────────────────────────────────────
            [
                'name' => 'Dress Midi Floral Rayon Premium Wanita',
                'description' => 'Dress midi dengan motif floral cerah berbahan rayon premium lembut adem. Lengan pendek, cocok untuk acara casual hingga semi-formal. Ukuran S-XXL.',
                'price' => 249000,
                'weight' => 0.28,
                'stock' => 60,
                'condition' => 'new',
                'category' => 'Pakaian wanita',
            ],
            [
                'name' => 'Blouse Wanita Oversize Linen Aesthetic',
                'description' => 'Blouse oversize bahan linen premium yang adem dan breathable. Potongan boxy modern, cocok untuk OOTD casual maupun ke kantor. Tersedia 6 warna netral. Ukuran S-XL.',
                'price' => 189000,
                'weight' => 0.20,
                'stock' => 80,
                'condition' => 'new',
                'category' => 'Pakaian wanita',
            ],
            [
                'name' => 'Rok Plisket Midi Wanita Chiffon',
                'description' => 'Rok plisket midi berbahan chiffon premium. Karet pinggang elastis nyaman, panjang selutut. Cocok untuk kerja, kuliah, atau acara kasual. Ukuran S-XL.',
                'price' => 159000,
                'weight' => 0.20,
                'stock' => 90,
                'condition' => 'new',
                'category' => 'Pakaian wanita',
            ],
            [
                'name' => 'Celana Kulot Wanita Pleated Wide Leg',
                'description' => 'Celana kulot wide-leg berbahan woven premium bertekstur. Desain elegant dan nyaman, cocok untuk kantoran dan acara semi-formal. Pinggang high-waist elastis. Ukuran S-XL.',
                'price' => 219000,
                'weight' => 0.30,
                'stock' => 70,
                'condition' => 'new',
                'category' => 'Pakaian wanita',
            ],
            [
                'name' => 'Cardigan Rajut Premium Wanita Korean Style',
                'description' => 'Cardigan rajut wanita gaya Korea dengan bahan akrilik lembut premium. Desain oversized dengan 2 saku depan, berbagai warna pastel dan earthy tone. Ukuran S-XL.',
                'price' => 279000,
                'weight' => 0.35,
                'stock' => 50,
                'condition' => 'new',
                'category' => 'Pakaian wanita',
            ],
            [
                'name' => 'Baju Atasan Wanita Kemeja Batik Tulis Solo',
                'description' => 'Kemeja batik tulis asli Solo, motif parang dan kawung, bahan katun prima halus. Dibuat pengrajin lokal. Cocok untuk formal, wisuda, kondangan. Ukuran S-3XL.',
                'price' => 349000,
                'weight' => 0.30,
                'stock' => 35,
                'condition' => 'new',
                'category' => 'Pakaian wanita',
            ],
            [
                'name' => 'Set Setelan Wanita Crop Top & High Waist Pants',
                'description' => 'Setelan dua potong wanita crop top + celana high waist berbahan linen katun. Desain minimalis modern. Cocok untuk hangout, foto OOTD, dan acara kasual. Ukuran S-XL.',
                'price' => 319000,
                'weight' => 0.40,
                'stock' => 45,
                'condition' => 'new',
                'category' => 'Pakaian wanita',
            ],
            [
                'name' => 'Gamis Syari Wanita Jersey Premium Polos',
                'description' => 'Gamis syari berbahan jersey premium stretch yang nyaman dipakai. Tidak mudah kusut, cocok untuk sehari-hari maupun acara formal islami. Berbagai pilihan warna. Ukuran S-5XL.',
                'price' => 189000,
                'weight' => 0.40,
                'stock' => 100,
                'condition' => 'new',
                'category' => 'Pakaian wanita',
            ],

            // ─────────────────────────────────────────
            // SKINCARE
            // ─────────────────────────────────────────
            [
                'name' => 'SOMETHINC Niacinamide 10% + Zinc 1% Serum',
                'description' => 'Serum niacinamide 10% + zinc 1% untuk mengecilkan pori, mengontrol minyak, dan mencerahkan kulit. Cocok untuk kulit berminyak dan kombinasi. BPOM terdaftar.',
                'price' => 99000,
                'weight' => 0.12,
                'stock' => 150,
                'condition' => 'new',
                'category' => 'Skincare',
            ],
            [
                'name' => 'Wardah Hydrating Aloe Vera Gel 100ml',
                'description' => 'Gel aloe vera Wardah dengan kandungan 99% aloe barbadensis, menenangkan kulit kemerahan dan sunburn, melembabkan. Cocok untuk semua jenis kulit. Tersertifikasi halal.',
                'price' => 39000,
                'weight' => 0.15,
                'stock' => 300,
                'condition' => 'new',
                'category' => 'Skincare',
            ],
            [
                'name' => 'COSRX Advanced Snail 96 Mucin Power Essence 100ml',
                'description' => 'Essence viral 96% snail secretion filtrate untuk repair kulit, melembabkan intensif, dan mencerahkan bekas jerawat. Best seller K-beauty global. Cocok semua jenis kulit.',
                'price' => 195000,
                'weight' => 0.15,
                'stock' => 100,
                'condition' => 'new',
                'category' => 'Skincare',
            ],
            [
                'name' => 'Scarlett Whitening Body Lotion Charming 300ml',
                'description' => 'Body lotion Scarlett Whitening Charming dengan ekstrak mulberry, kojic acid, dan niacinamide untuk mencerahkan kulit tubuh. Wangi tahan lama, tidak lengket.',
                'price' => 75000,
                'weight' => 0.38,
                'stock' => 200,
                'condition' => 'new',
                'category' => 'Skincare',
            ],
            [
                'name' => 'Azarine Hydrasoothe Sunscreen SPF 50+ PA++++',
                'description' => 'Sunscreen dengan SPF 50+ PA++++ dengan blue light protection. Formula watery ringan tidak white cast, tidak lengket. Melembabkan dan melindungi kulit. Cocok untuk wajah sehari-hari.',
                'price' => 89000,
                'weight' => 0.10,
                'stock' => 120,
                'condition' => 'new',
                'category' => 'Skincare',
            ],
            [
                'name' => 'The Ordinary Hyaluronic Acid 2% + B5 30ml',
                'description' => 'Serum hyaluronic acid 2% + vitamin B5 dari The Ordinary. Melembabkan kulit berlapis, menjaga skin barrier, tekstur sangat ringan. Cocok semua jenis kulit termasuk sensitif.',
                'price' => 139000,
                'weight' => 0.06,
                'stock' => 80,
                'condition' => 'new',
                'category' => 'Skincare',
            ],
            [
                'name' => 'Emina Bright Stuff Face Moisturizing Lotion 60ml',
                'description' => 'Moisturizer Emina Bright Stuff dengan niacinamide dan vitamin C. Formula ringan tidak comedogenic, cocok untuk kulit remaja berminyak dan kombinasi. Harga terjangkau.',
                'price' => 33000,
                'weight' => 0.10,
                'stock' => 250,
                'condition' => 'new',
                'category' => 'Skincare',
            ],
            [
                'name' => 'Cetaphil Gentle Skin Cleanser 250ml',
                'description' => 'Sabun cuci muka Cetaphil untuk kulit sensitif dan kering. Formula lembut, tidak mengandung detergen keras, dermatologist-tested. Membersihkan tanpa mengganggu skin barrier.',
                'price' => 119000,
                'weight' => 0.30,
                'stock' => 90,
                'condition' => 'new',
                'category' => 'Skincare',
            ],

            // ─────────────────────────────────────────
            // SUPLEMEN
            // ─────────────────────────────────────────
            [
                'name' => 'Blackmores Bio C 1000mg 150 Tablets',
                'description' => 'Suplemen vitamin C Blackmores Bio C 1000mg dengan bioflavonoid untuk meningkatkan imunitas, antioksidan kuat. Formula sustained release. 150 tablet untuk 5 bulan.',
                'price' => 299000,
                'weight' => 0.25,
                'stock' => 80,
                'condition' => 'new',
                'category' => 'Suplemen',
            ],
            [
                'name' => 'Optimum Nutrition Gold Standard Whey Protein 2lbs Chocolate',
                'description' => 'Whey protein ON Gold Standard 2lbs dengan 24g protein per serving, 5.5g BCAA alami. Brand protein no.1 dunia. Cocok untuk muscle gain dan recovery pasca latihan.',
                'price' => 449000,
                'weight' => 1.00,
                'stock' => 30,
                'condition' => 'new',
                'category' => 'Suplemen',
            ],
            [
                'name' => 'Enervon-C Multivitamin 30 Tablet',
                'description' => 'Multivitamin Enervon-C dengan Vitamin C 500mg, B1, B2, B6, B12, niacinamide, dan kalsium pantotenat. Menjaga stamina dan daya tahan tubuh harian. Original resmi.',
                'price' => 39000,
                'weight' => 0.08,
                'stock' => 300,
                'condition' => 'new',
                'category' => 'Suplemen',
            ],
            [
                'name' => "Nature's Plus Omega-3 Fish Oil 1000mg 60 Softgels",
                'description' => 'Suplemen Omega-3 fish oil 1000mg dengan EPA 180mg dan DHA 120mg. Mendukung kesehatan jantung, otak, dan sendi. Tanpa merkuri, no fishy aftertaste.',
                'price' => 199000,
                'weight' => 0.12,
                'stock' => 60,
                'condition' => 'new',
                'category' => 'Suplemen',
            ],
            [
                'name' => 'Vitamin D3 5000 IU 180 Softgels',
                'description' => 'Suplemen Vitamin D3 5000 IU dosis tinggi untuk mendukung kesehatan tulang, imunitas, dan mood. Formula olive oil-based untuk absorpsi maksimal. 180 softgel untuk 6 bulan.',
                'price' => 159000,
                'weight' => 0.15,
                'stock' => 70,
                'condition' => 'new',
                'category' => 'Suplemen',
            ],
            [
                'name' => 'Hemaviton Stamina Plus Energy Drink 6x150ml',
                'description' => 'Minuman suplemen energi Hemaviton Stamina Plus dengan ginseng, royal jelly, dan vitamin B kompleks. Mengatasi kelelahan dan meningkatkan stamina. Pack isi 6 botol.',
                'price' => 42000,
                'weight' => 1.00,
                'stock' => 200,
                'condition' => 'new',
                'category' => 'Suplemen',
            ],
            [
                'name' => 'Magnesium Glycinate 400mg 120 Capsules',
                'description' => 'Suplemen magnesium glycinate 400mg dengan bioavailabilitas tinggi. Membantu tidur lebih nyenyak, mengurangi kram otot, mendukung kesehatan saraf. Gentle di lambung.',
                'price' => 229000,
                'weight' => 0.13,
                'stock' => 50,
                'condition' => 'new',
                'category' => 'Suplemen',
            ],

            // ─────────────────────────────────────────
            // SECOND HAND
            // ─────────────────────────────────────────
            [
                'name' => 'iPhone 13 Pro 256GB Sierra Blue (Second)',
                'description' => 'iPhone 13 Pro 256GB kondisi mulus 95%. Battery health 89%, tidak ada goresan signifikan. Fullset: box, charger USB-C, handsfree. Garansi toko 1 bulan.',
                'price' => 10500000,
                'weight' => 0.20,
                'stock' => 2,
                'condition' => 'second',
                'category' => 'Smartphone',
            ],
            [
                'name' => 'Samsung Galaxy S23 128GB Phantom Black (Second)',
                'description' => 'Samsung Galaxy S23 128GB second mulus 92%. Snapdragon 8 Gen 2, kamera 50MP, layar Dynamic AMOLED 6.1 inci. Battery masih oke, charger original. Garansi toko.',
                'price' => 7200000,
                'weight' => 0.17,
                'stock' => 3,
                'condition' => 'second',
                'category' => 'Smartphone',
            ],
            [
                'name' => 'MacBook Air M1 8GB/256GB Gold (Second)',
                'description' => 'MacBook Air M1 2020 Gold, 8GB RAM, 256GB SSD. Kondisi 90%, cycle count 180, battery health baik. Fullset box charger. Ideal untuk mahasiswa dan profesional.',
                'price' => 8500000,
                'weight' => 1.29,
                'stock' => 2,
                'condition' => 'second',
                'category' => 'Laptop',
            ],
            [
                'name' => 'Sony WH-1000XM5 (Second)',
                'description' => 'Headphone Sony WH-1000XM5 kondisi 93%, ANC terbaik di kelasnya. 30 jam baterai, lipatan desain baru, USB-C. Fullset dengan pouch, kabel. Tidak ada kerusakan.',
                'price' => 2999000,
                'weight' => 0.25,
                'stock' => 2,
                'condition' => 'second',
                'category' => 'Aksesoris gadget',
            ],
            [
                'name' => 'Nintendo Switch OLED Putih + 2 Games (Second)',
                'description' => 'Nintendo Switch OLED White kondisi 92%. Layar OLED 7 inci mulus, termasuk Mario Kart 8 Deluxe dan Super Mario Odyssey. Dock dan JoyCon lengkap. Garansi toko.',
                'price' => 4100000,
                'weight' => 0.42,
                'stock' => 1,
                'condition' => 'second',
                'category' => 'Aksesoris gadget',
            ],
            [
                'name' => 'iPad Air 5 64GB WiFi Space Gray (Second)',
                'description' => 'iPad Air generasi 5 dengan chip M1, layar Liquid Retina 10.9 inci, WiFi 6. Kondisi 91%, tidak ada dead pixel. Fullset Apple Pencil Gen 1 bonus. Garansi toko 1 bulan.',
                'price' => 7800000,
                'weight' => 0.46,
                'stock' => 2,
                'condition' => 'second',
                'category' => 'Aksesoris gadget',
            ],
        ];

        $categories = ProductCategory::all()->keyBy('name');

        foreach ($products as $productData) {
            $categoryName = $productData['category'];
            unset($productData['category']);

            $category = $categories->get($categoryName);
            if (! $category) {
                $this->command->warn("Category '{$categoryName}' not found. Skipping: {$productData['name']}");

                continue;
            }

            $store = $stores->random();

            $product = Product::create([
                'store_id' => $store->id,
                'product_category_id' => $category->id,
                'name' => $productData['name'],
                'slug' => Str::slug($productData['name']).'-'.rand(100, 999),
                'description' => $productData['description'],
                'condition' => $productData['condition'],
                'price' => $productData['price'],
                'weight' => $productData['weight'],
                'stock' => $productData['stock'],
            ]);

            ProductImage::factory()->thumbnail()->create([
                'product_id' => $product->id,
            ]);

            $extraImages = rand(1, 3);
            ProductImage::factory()->count($extraImages)->create([
                'product_id' => $product->id,
            ]);
        }

        $this->command->info('Created '.count($products).' products with realistic Indonesian market data.');
    }
}
