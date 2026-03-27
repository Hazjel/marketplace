<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $storeOwner = User::factory()->create();
        $storeOwner->guard_name = 'sanctum';
        $storeOwner->assignRole('store');
        $storeOwner->refresh();

        $this->store = Store::create([
            'user_id' => $storeOwner->id,
            'name' => 'Test Store',
            'username' => 'teststore',
            'logo' => 'https://example.com/logo.png',
            'city' => 'Bandung',
            'address' => 'Jl. Test 123',
            'address_id' => '1',
            'phone' => '081234567890',
            'postal_code' => '40000',
            'about' => 'Test store desc',
        ]);

        $category = ProductCategory::create([
            'name' => 'Elektronik',
            'slug' => 'elektronik',
            'description' => 'Kategori elektronik',
        ]);

        $this->product = Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'store_id' => $this->store->id,
            'product_category_id' => $category->id,
            'description' => 'A test product',
            'price' => 100000,
            'stock' => 50,
            'weight' => 500,
            'condition' => 'new',
        ]);

        $this->user = User::factory()->create();
        $this->user->guard_name = 'sanctum';
        $this->user->assignRole('buyer');
        $this->user->refresh();
    }

    public function test_authenticated_user_can_add_item_to_cart(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart', [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]);



        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.product_id', $this->product->id)
            ->assertJsonPath('data.quantity', 2);
    }

    public function test_duplicate_add_increments_quantity(): void
    {
        // Add first time
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart', [
                'product_id' => $this->product->id,
                'quantity' => 3,
            ]);

        // Add again — should increment
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart', [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.quantity', 5);
    }

    public function test_user_can_get_cart(): void
    {
        // Add item first
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart', [
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/cart');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    ['store_id', 'store_name', 'items'],
                ],
            ]);
    }

    public function test_user_can_update_quantity(): void
    {
        // Add item first
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart', [
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/cart/{$this->product->id}", [
                'quantity' => 10,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.quantity', 10);
    }

    public function test_user_can_remove_item(): void
    {
        // Add then remove
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart', [
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/cart/{$this->product->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify cart is empty via GET
        $getResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/cart');

        $getResponse->assertJsonCount(0, 'data');
    }

    public function test_user_can_clear_cart(): void
    {
        // Add item first
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart', [
                'product_id' => $this->product->id,
                'quantity' => 3,
            ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson('/api/cart/clear');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify cart is empty via GET
        $getResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/cart');

        $getResponse->assertJsonCount(0, 'data');
    }

    public function test_sync_merges_local_cart(): void
    {
        // Add existing server cart with qty 2
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart', [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]);

        // Sync from local with qty 5 — should keep the higher (5)
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/sync', [
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 5,
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify merged qty via GET
        $getResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/cart');

        $items = $getResponse->json('data.0.items');
        $this->assertEquals(5, $items[0]['quantity']);
    }

    public function test_validate_stock_returns_valid(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/validate-stock', [
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 10,
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.all_valid', true)
            ->assertJsonPath('data.items.0.valid', true);
    }

    public function test_validate_stock_flags_insufficient(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cart/validate-stock', [
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 999,
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.all_valid', false)
            ->assertJsonPath('data.items.0.valid', false)
            ->assertJsonPath('data.items.0.reason', 'insufficient_stock');
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $response = $this->getJson('/api/cart');

        $response->assertStatus(401);
    }
}
