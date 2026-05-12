<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use App\Models\ProductCategory;
use Illuminate\Support\Str;
use App\Helpers\ImageHelper\ImageHelper;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $imageHelper = new ImageHelper;
        $stores = Store::all();
        
        if ($stores->isEmpty()) {
            $this->command->warn('No stores found. Skipping ProductSeeder.');
            return;
        }

        $products = [
            // Elektronik - Smartphone
            [
                'name' => 'iPhone 15 Pro Max 256GB',
                'description' => 'iPhone 15 Pro Max dengan chip A17 Pro, kamera 48MP, layar Super Retina XDR 6.7 inci. Titanium design, USB-C, Action Button.',
                'price' => 21999000,
                'weight' => 0.22,
                'stock' => 15,
                'condition' => 'new',
                'category' => 'Smartphone',
            ],
            [
                'name' => 'Samsung Galaxy S24 Ultra 512GB',
                'description' => 'Samsung Galaxy S24 Ultra dengan Snapdragon 8 Gen 3, S Pen built-in, kamera 200MP, layar Dynamic AMOLED 2X 6.8 inci. Galaxy AI powered.',
                'price' => 23499000,
                'weight' => 0.23,
                'stock' => 12,
                'condition' => 'new',
                'category' => 'Smartphone',
            ],
            [
                'name' => 'Xiaomi 14 Ultra 512GB',
                'description' => 'Xiaomi 14 Ultra dengan Leica optics, Snapdragon 8 Gen 3, layar LTPO AMOLED 6.73 inci, fast charging 90W.',
                'price' => 14999000,
                'weight' => 0.22,
                'stock' => 20,
                'condition' => 'new',
                'category' => 'Smartphone',
            ],
            [
                'name' => 'OPPO Find X7 Ultra',
                'description' => 'OPPO Find X7 Ultra dengan Hasselblad Camera System, dual periscope telephoto, Snapdragon 8 Gen 3.',
                'price' => 16999000,
                'weight' => 0.22,
                'stock' => 8,
                'condition' => 'new',
                'category' => 'Smartphone',
            ],
            [
                'name' => 'Google Pixel 8 Pro 256GB',
                'description' => 'Google Pixel 8 Pro dengan Tensor G3 chip, AI photography terbaik, 7 tahun update OS.',
                'price' => 13499000,
                'weight' => 0.21,
                'stock' => 10,
                'condition' => 'new',
                'category' => 'Smartphone',
            ],

            // Elektronik - Laptop
            [
                'name' => 'MacBook Pro 14" M3 Pro 18GB/512GB',
                'description' => 'MacBook Pro 14 inci dengan chip M3 Pro, 18GB unified memory, 512GB SSD. Liquid Retina XDR display, 17 jam battery life.',
                'price' => 29999000,
                'weight' => 1.6,
                'stock' => 8,
                'condition' => 'new',
                'category' => 'Laptop',
            ],
            [
                'name' => 'ASUS ROG Strix G16 RTX 4060',
                'description' => 'Laptop gaming ASUS ROG Strix G16 dengan Intel Core i7-13650HX, NVIDIA RTX 4060 8GB, RAM 16GB DDR5, SSD 512GB. Layar 165Hz.',
                'price' => 18999000,
                'weight' => 2.5,
                'stock' => 6,
                'condition' => 'new',
                'category' => 'Laptop',
            ],
            [
                'name' => 'Lenovo ThinkPad X1 Carbon Gen 11',
                'description' => 'Ultrabook premium Lenovo ThinkPad X1 Carbon dengan Intel Core i7-1365U, RAM 16GB, SSD 512GB. Berat hanya 1.12kg, layar 14" 2.8K OLED.',
                'price' => 24999000,
                'weight' => 1.12,
                'stock' => 5,
                'condition' => 'new',
                'category' => 'Laptop',
            ],
            [
                'name' => 'Acer Nitro V 15 RTX 4050',
                'description' => 'Laptop gaming budget-friendly Acer Nitro V dengan Intel Core i5-13420H, RTX 4050 6GB, RAM 16GB, SSD 512GB. Layar FHD 144Hz.',
                'price' => 12499000,
                'weight' => 2.1,
                'stock' => 15,
                'condition' => 'new',
                'category' => 'Laptop',
            ],
            [
                'name' => 'HP Pavilion 14 Ryzen 5 7530U',
                'description' => 'Laptop everyday HP Pavilion 14 dengan AMD Ryzen 5 7530U, RAM 8GB, SSD 512GB. Ringan, cocok untuk mahasiswa dan pekerja kantoran.',
                'price' => 8499000,
                'weight' => 1.4,
                'stock' => 25,
                'condition' => 'new',
                'category' => 'Laptop',
            ],

            // Aksesoris Gadget
            [
                'name' => 'Apple AirPods Pro 2nd Gen (USB-C)',
                'description' => 'AirPods Pro generasi 2 dengan USB-C, Active Noise Cancellation, Adaptive Transparency, Personalized Spatial Audio.',
                'price' => 3799000,
                'weight' => 0.05,
                'stock' => 30,
                'condition' => 'new',
                'category' => 'Aksesoris gadget',
            ],
            [
                'name' => 'Samsung Galaxy Watch 6 Classic 47mm',
                'description' => 'Smartwatch premium Samsung dengan rotating bezel, Wear OS by Samsung, BIA sensor, GPS, water resistant 5ATM.',
                'price' => 5499000,
                'weight' => 0.06,
                'stock' => 12,
                'condition' => 'new',
                'category' => 'Aksesoris gadget',
            ],
            [
                'name' => 'Anker PowerCore 26800mAh',
                'description' => 'Power bank kapasitas besar Anker PowerCore 26800mAh dengan 3 port USB output, input micro USB & USB-C. PowerIQ fast charging.',
                'price' => 599000,
                'weight' => 0.5,
                'stock' => 50,
                'condition' => 'new',
                'category' => 'Aksesoris gadget',
            ],
            [
                'name' => 'Logitech MX Master 3S Wireless Mouse',
                'description' => 'Mouse wireless premium Logitech MX Master 3S dengan sensor 8000 DPI, quiet clicks, MagSpeed scroll wheel, USB-C charging. Multi-device.',
                'price' => 1499000,
                'weight' => 0.14,
                'stock' => 20,
                'condition' => 'new',
                'category' => 'Aksesoris gadget',
            ],
            [
                'name' => 'Keychron K8 Pro Mechanical Keyboard',
                'description' => 'Mechanical keyboard wireless Keychron K8 Pro, Gateron G Pro switch, hot-swappable, RGB backlight, Mac & Windows layout.',
                'price' => 1299000,
                'weight' => 0.95,
                'stock' => 18,
                'condition' => 'new',
                'category' => 'Aksesoris gadget',
            ],

            // Fashion - Pakaian Pria
            [
                'name' => 'Kaos Polo Premium Cotton Pique',
                'description' => 'Kaos polo pria berbahan cotton pique premium 240gsm. Nyaman, tidak mudah kusut, cocok untuk casual dan semi-formal.',
                'price' => 189000,
                'weight' => 0.25,
                'stock' => 100,
                'condition' => 'new',
                'category' => 'Pakaian pria',
            ],
            [
                'name' => 'Celana Chino Slim Fit Stretch',
                'description' => 'Celana chino slim fit dengan bahan stretch cotton blend. Nyaman untuk aktivitas seharian, tersedia berbagai warna.',
                'price' => 249000,
                'weight' => 0.4,
                'stock' => 80,
                'condition' => 'new',
                'category' => 'Pakaian pria',
            ],
            [
                'name' => 'Jaket Bomber Waterproof',
                'description' => 'Jaket bomber pria dengan bahan parasut waterproof. Inner fleece hangat, cocok untuk musim hujan dan outdoor.',
                'price' => 349000,
                'weight' => 0.6,
                'stock' => 40,
                'condition' => 'new',
                'category' => 'Pakaian pria',
            ],

            // Kesehatan & Kecantikan - Skincare
            [
                'name' => 'SOMETHINC Niacinamide Moisture Sabi Beet Serum',
                'description' => 'Serum dengan Niacinamide 5% + Sabi Beet untuk mencerahkan, mengecilkan pori, dan melembabkan kulit. Cocok untuk semua jenis kulit.',
                'price' => 89000,
                'weight' => 0.1,
                'stock' => 200,
                'condition' => 'new',
                'category' => 'Skincare',
            ],
            [
                'name' => 'COSRX Advanced Snail 96 Mucin Power Essence',
                'description' => 'Essence dengan 96% Snail Secretion Filtrate untuk repair, hydrate, dan mencerahkan kulit. Best seller global.',
                'price' => 195000,
                'weight' => 0.12,
                'stock' => 100,
                'condition' => 'new',
                'category' => 'Skincare',
            ],

            // Second hand items
            [
                'name' => 'iPhone 13 Pro 128GB (Second)',
                'description' => 'iPhone 13 Pro 128GB kondisi 95%. Battery health 87%, fullset box charger. Garansi toko 1 bulan.',
                'price' => 9500000,
                'weight' => 0.20,
                'stock' => 3,
                'condition' => 'second',
                'category' => 'Smartphone',
            ],
            [
                'name' => 'MacBook Air M1 2020 8/256GB (Second)',
                'description' => 'MacBook Air M1 2020, RAM 8GB, SSD 256GB. Kondisi 90%, cycle count 120. Fullset box.',
                'price' => 8999000,
                'weight' => 1.3,
                'stock' => 2,
                'condition' => 'second',
                'category' => 'Laptop',
            ],
            [
                'name' => 'Sony WH-1000XM4 (Second)',
                'description' => 'Headphone wireless Sony WH-1000XM4 kondisi 92%. Active Noise Cancelling, 30 jam battery. Fullset.',
                'price' => 2499000,
                'weight' => 0.25,
                'stock' => 4,
                'condition' => 'second',
                'category' => 'Aksesoris gadget',
            ],
            [
                'name' => 'Nintendo Switch OLED + 3 Games (Second)',
                'description' => 'Nintendo Switch OLED Model White kondisi 93%. Include 3 game cartridge (Zelda TOTK, Mario Kart 8, Animal Crossing).',
                'price' => 4200000,
                'weight' => 0.42,
                'stock' => 2,
                'condition' => 'second',
                'category' => 'Elektronik',
            ],
        ];

        // Get all categories indexed by name
        $categories = ProductCategory::all()->keyBy('name');

        foreach ($products as $productData) {
            $categoryName = $productData['category'];
            unset($productData['category']);

            // Find category (try child first, then parent)
            $category = $categories->get($categoryName);
            if (!$category) {
                // Skip if category not found
                $this->command->warn("Category '{$categoryName}' not found. Skipping product: {$productData['name']}");
                continue;
            }

            // Assign to random store
            $store = $stores->random();

            $product = Product::create([
                'store_id' => $store->id,
                'product_category_id' => $category->id,
                'name' => $productData['name'],
                'slug' => Str::slug($productData['name']) . '-' . rand(100, 999),
                'description' => $productData['description'],
                'condition' => $productData['condition'],
                'price' => $productData['price'],
                'weight' => $productData['weight'],
                'stock' => $productData['stock'],
            ]);

            // Create product images (1 thumbnail + 1-3 additional)
            ProductImage::factory()->thumbnail()->create([
                'product_id' => $product->id,
            ]);

            $extraImages = rand(1, 3);
            ProductImage::factory()->count($extraImages)->create([
                'product_id' => $product->id,
            ]);
        }

        $this->command->info('Created ' . count($products) . ' products with realistic data.');
    }
}
