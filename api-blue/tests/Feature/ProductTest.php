<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_owner_can_create_product()
    {
        // Setup
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

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

    public function test_seller_cannot_create_product_for_another_stores_id(): void
    {
        // store_id sebelumnya dipercaya mentah-mentah dari request -- seller
        // A bisa titip store_id milik seller B, produk (spam/palsu) muncul
        // di toko kompetitor. store_id sekarang di-derive server-side dari
        // toko milik pemanggil, bukan dari payload.
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $sellerA = User::factory()->create();
        $sellerA->assignRole('store');
        $storeA = Store::factory()->create(['user_id' => $sellerA->id]);

        $sellerB = User::factory()->create();
        $sellerB->assignRole('store');
        $storeB = Store::factory()->create(['user_id' => $sellerB->id]);

        $parent = ProductCategory::create(['name' => 'Elektronik', 'slug' => 'elektronik-spoof', 'description' => 'E']);
        $category = ProductCategory::create([
            'name' => 'HP', 'slug' => 'hp-spoof', 'description' => 'HP', 'parent_id' => $parent->id,
        ]);

        $response = $this->actingAs($sellerA, 'sanctum')->postJson('/api/product', [
            'store_id' => $storeB->id, // titip store_id milik seller LAIN
            'product_category_id' => $category->id,
            'name' => 'Produk Titipan',
            'description' => 'desc',
            'price' => 10000,
            'stock' => 1,
            'weight' => 100,
            'condition' => 'new',
            'product_images' => [
                ['image' => UploadedFile::fake()->image('p.jpg'), 'is_thumbnail' => true],
            ],
        ]);

        $response->assertStatus(201);
        // Produk harus jatuh ke toko A (pemanggil), BUKAN toko B yang dititipkan.
        $this->assertDatabaseHas('products', ['name' => 'Produk Titipan', 'store_id' => $storeA->id]);
        $this->assertDatabaseMissing('products', ['name' => 'Produk Titipan', 'store_id' => $storeB->id]);
    }

    public function test_product_from_inactive_store_returns_404_via_direct_lookup(): void
    {
        // getById()/getBySlug() sendiri tidak memfilter store.is_active
        // (dipakai bareng update()/destroy() untuk akses pemilik, yang
        // sengaja is_active-agnostic) -- filternya ada di controller,
        // pola sama seperti StoreController::show(). Tanpa ini, produk
        // toko nonaktif tetap bisa dibuka langsung kalau ID/slug-nya
        // diketahui, walau sudah hilang dari listing/search publik.
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $seller = User::factory()->create();
        $seller->assignRole('store');
        $store = Store::factory()->create(['user_id' => $seller->id, 'is_active' => false]);

        $parent = ProductCategory::create(['name' => 'G', 'slug' => 'g-inactive-store', 'description' => 'G']);
        $category = ProductCategory::create([
            'name' => 'H', 'slug' => 'h-inactive-store', 'description' => 'H', 'parent_id' => $parent->id,
        ]);
        $product = Product::create([
            'store_id' => $store->id, 'product_category_id' => $category->id,
            'name' => 'Produk Toko Nonaktif', 'slug' => 'produk-toko-nonaktif',
            'description' => 'D', 'condition' => 'new', 'price' => 10000, 'weight' => 100, 'stock' => 1,
        ]);

        $this->getJson("/api/product/{$product->id}")
            ->assertStatus(404)
            ->assertJson(['success' => true, 'data' => null]);

        $this->getJson("/api/product/slug/{$product->slug}")
            ->assertStatus(404)
            ->assertJson(['success' => true, 'data' => null]);
    }
}
