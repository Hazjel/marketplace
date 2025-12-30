<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Reset cache permission
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * Path 1: Admin mencoba checkout (Forbidden)
     * Node: 1 -> 2 -> 3 -> 4 -> 12
     */
    public function test_path_1_admin_cannot_create_transaction()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/transaction', []);

        // Node 4: Return 403 Forbidden
        $response->assertStatus(403); 
    }

    /**
     * Path 2: Validasi Gagal (Data tidak lengkap)
     * Node: 1 -> 2 -> 3 -> 5 -> 6 -> 7 -> 12
     */
    public function test_path_2_validation_fails_with_empty_payload()
    {
        $user = User::factory()->create();
        $user->assignRole('buyer');

        // Payload kosong
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/transaction', []);

        // Node 7: Return 422 Unprocessable Entity
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['buyer_id', 'store_id', 'products']);
    }

    /**
     * Path 3: Checkout Berhasil (Happy Path)
     * Node: 1 -> 2 -> 3 -> 5 -> 6 -> 8 -> 9 -> 10 -> 12
     */
    public function test_path_3_checkout_success()
    {
        // 1. Setup Seller & Store
        $seller = User::factory()->create();
        $seller->assignRole('store');

        $store = Store::create([
            'user_id' => $seller->id,
            'name' => 'Seller Store',
            'username' => 'sellerstore',
            'logo' => 'default.png',
            'about' => 'About',
            'phone' => '08123456789',
            'address_id' => '1',
            'city' => 'Jakarta',
            'address' => 'Jl. Seller',
            'postal_code' => '12345',
            'is_verified' => true
        ]);
        
        // Init Store Balance
        $store->storeBalance()->create(['balance' => 0]);

        // 2. Setup Product
        $category = ProductCategory::create([
            'name' => 'General', 'slug' => 'general', 'description' => 'General'
        ]);
        
        $product = Product::create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'description' => 'Test Desc',
            'price' => 10000,
            'stock' => 10,
            'weight' => 1,
            'condition' => 'new'
        ]);

        // 3. Setup Buyer
        $buyerUser = User::factory()->create();
        $buyerUser->assignRole('buyer');
        
        // Ensure Buyer profile exists
        $buyerProfile = $buyerUser->buyer()->create([
            'phone_number' => '08987654321',
            'city' => 'Bandung',
            'address' => 'Jl. Buyer'
        ]);

        // 4. Prepare Payload
        $payload = [
            'buyer_id' => $buyerProfile->id,
            'store_id' => $store->id,
            'address_id' => 101, // Dummy ID
            'address' => 'Jl. Pengiriman',
            'city' => 'Surabaya',
            'postal_code' => '60000',
            'shipping' => 'JNE',
            'shipping_type' => 'REG',
            'shipping_cost' => 15000,
            'products' => [
                [
                    'product_id' => $product->id,
                    'qty' => 2
                ]
            ]
        ];

        // 5. Execute API
        $response = $this->actingAs($buyerUser, 'sanctum')->postJson('/api/transaction', $payload);

        // Node 10: Return 201 Created
        $response->assertStatus(201)
                 ->assertJsonStructure(['data' => ['id', 'grand_total', 'delivery_status']]);
        
        // Optional Database Check
        $this->assertDatabaseHas('transactions', [
            'buyer_id' => $buyerProfile->id,
            'store_id' => $store->id
        ]);
    }
}
