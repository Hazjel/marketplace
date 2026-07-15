<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_owner_can_create_product()
    {
        // Setup
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('store');

        $store = Store::factory()->create(['user_id' => $user->id]);

        $parent = ProductCategory::create([
            'name' => 'Fashion',
            'slug' => 'fashion',
            'description' => 'Baju',
        ]);

        // Produk hanya boleh menempel ke subkategori (punya induk)
        $category = ProductCategory::create([
            'name' => 'Kaos',
            'slug' => 'kaos',
            'description' => 'Kaos-kaosan',
            'parent_id' => $parent->id,
        ]);

        $payload = [
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Kaos Polos',
            'description' => 'Kaos nyaman',
            'price' => 50000,
            'stock' => 100,
            'weight' => 200,
            'condition' => 'new',
            'product_images' => [
                ['image' => UploadedFile::fake()->image('photo1.jpg'), 'is_thumbnail' => true],
            ],
        ];

        // Act
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/product', $payload);

        // Assert
        $response->assertStatus(201);
        // Slug digenerate dengan suffix unik (mis. kaos-polos-i417522...)
        $this->assertDatabaseHas('products', ['name' => 'Kaos Polos']);
    }
}
