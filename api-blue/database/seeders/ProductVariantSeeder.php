<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductVariantMongo;
use App\Models\ProductCategory;

class ProductVariantSeeder extends Seeder
{
    public function run(): void
    {
        ProductVariantMongo::truncate();

        $smartphoneCat = ProductCategory::where('slug', 'smartphone')->first();
        $laptopCat     = ProductCategory::where('slug', 'laptop')->first();

        $variants = [
            // ── Smartphone variants ──────────────────────────────────────────
            'iPhone 15 Pro Max 256GB Natural Titanium' => [
                ['RAM' => '8GB', 'Storage' => '256GB'],
                ['RAM' => '8GB', 'Storage' => '512GB'],
                ['RAM' => '8GB', 'Storage' => '1TB'],
            ],
            'Samsung Galaxy S24 Ultra 512GB Titanium Black' => [
                ['RAM' => '12GB', 'Storage' => '256GB'],
                ['RAM' => '12GB', 'Storage' => '512GB'],
                ['RAM' => '12GB', 'Storage' => '1TB'],
            ],
            'Xiaomi 14 Ultra 512GB Black' => [
                ['RAM' => '12GB', 'Storage' => '256GB'],
                ['RAM' => '16GB', 'Storage' => '512GB'],
            ],
            'OPPO Find X8 Pro 256GB Black' => [
                ['RAM' => '12GB', 'Storage' => '256GB'],
                ['RAM' => '16GB', 'Storage' => '512GB'],
            ],
            'Samsung Galaxy A55 5G 256GB Awesome Navy' => [
                ['RAM' => '8GB',  'Storage' => '128GB'],
                ['RAM' => '8GB',  'Storage' => '256GB'],
            ],
            'Xiaomi Redmi Note 13 Pro 5G 256GB' => [
                ['RAM' => '8GB',  'Storage' => '128GB'],
                ['RAM' => '8GB',  'Storage' => '256GB'],
                ['RAM' => '12GB', 'Storage' => '256GB'],
            ],
            'POCO X6 Pro 5G 256GB Yellow' => [
                ['RAM' => '8GB',  'Storage' => '256GB'],
                ['RAM' => '12GB', 'Storage' => '256GB'],
                ['RAM' => '12GB', 'Storage' => '512GB'],
            ],
            'Realme GT 6 256GB Fluid Silver' => [
                ['RAM' => '8GB',  'Storage' => '256GB'],
                ['RAM' => '12GB', 'Storage' => '256GB'],
            ],
            'Vivo V30 5G 256GB Peacock Green' => [
                ['RAM' => '8GB',  'Storage' => '256GB'],
                ['RAM' => '12GB', 'Storage' => '256GB'],
            ],
            'Nothing Phone (2a) 256GB Black' => [
                ['RAM' => '8GB',  'Storage' => '256GB'],
                ['RAM' => '12GB', 'Storage' => '256GB'],
            ],
            // ── Laptop variants ──────────────────────────────────────────────
            'MacBook Pro 14" M3 Pro 18GB/512GB Silver' => [
                ['RAM' => '18GB', 'Storage' => '512GB'],
                ['RAM' => '18GB', 'Storage' => '1TB'],
                ['RAM' => '36GB', 'Storage' => '1TB'],
            ],
            'MacBook Air 15" M3 8GB/256GB Starlight' => [
                ['RAM' => '8GB',  'Storage' => '256GB'],
                ['RAM' => '16GB', 'Storage' => '256GB'],
                ['RAM' => '16GB', 'Storage' => '512GB'],
            ],
            'ASUS ROG Strix G16 RTX 4070 2024' => [
                ['RAM' => '16GB', 'Storage' => '512GB'],
                ['RAM' => '32GB', 'Storage' => '1TB'],
            ],
        ];

        // Price multiplier per Storage size
        $storageMultiplier = [
            '128GB' => 1.0,
            '256GB' => 1.0,
            '512GB' => 1.15,
            '1TB'   => 1.35,
        ];
        $ramMultiplier = [
            '8GB'  => 1.0,
            '12GB' => 1.05,
            '16GB' => 1.12,
            '18GB' => 1.0,
            '32GB' => 1.2,
            '36GB' => 1.3,
        ];

        $categoryIds = array_filter([
            $smartphoneCat?->id,
            $laptopCat?->id,
        ]);

        $products = Product::whereIn('product_category_id', $categoryIds)->get();

        $inserted = 0;
        foreach ($products as $product) {
            $attrList = $variants[$product->name] ?? null;
            if (!$attrList) continue;

            $basePrice = (float) $product->price;

            foreach ($attrList as $attrs) {
                $sm = $storageMultiplier[$attrs['Storage'] ?? '256GB'] ?? 1.0;
                $rm = $ramMultiplier[$attrs['RAM'] ?? '8GB']           ?? 1.0;
                $price = round($basePrice * $sm * $rm, -3); // round to nearest 1000

                $name = implode(' / ', array_values($attrs));

                ProductVariantMongo::create([
                    'product_id'        => $product->id,
                    'name'              => $name,
                    'variant_attributes'=> $attrs,
                    'price'             => $price,
                    'stock'             => rand(5, 30),
                    'sku'               => strtoupper(substr(preg_replace('/\s+/', '', $product->name), 0, 8)) . '-' . strtoupper(implode('-', array_values($attrs))),
                    'image'             => null,
                ]);
                $inserted++;
            }
        }

        $this->command->info("✅ ProductVariantSeeder: {$inserted} varian dibuat.");
    }
}
