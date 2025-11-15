<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductImage;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Product::factory()->count(100)->create()->each(function ($product) {
            // Create 1-5 images for each product
            $imageCount = rand(1, 5);

           // Create one thumbnail image
            ProductImage::factory()->thumbnail()->create([
                'product_id' => $product->id,
            ]);

            // Create the additional of the images
            if ($imageCount > 1) {
                ProductImage::factory()->count($imageCount - 1)->create([
                    'product_id' => $product->id,
                ]);
            }    
        });      
    }
}
