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

/**
 * B3.2a: base price and variant price must be whole rupiah. A fractional
 * price is a domain violation (Money is scale 0) and would make the
 * product un-checkoutable. Guard it at the create/update boundary — both
 * ProductStoreRequest and ProductUpdateRequest.
 *
 * See api-blue/docs/money-contract.md.
 */
class ProductPriceWholeRupiahTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Store $store;

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->seller = User::factory()->create();
        $this->seller->assignRole('store');
        $this->store = Store::factory()->create(['user_id' => $this->seller->id]);

        $parent = ProductCategory::create(['name' => 'P', 'slug' => 'p-price', 'description' => 'P']);
        $this->category = ProductCategory::create([
            'name' => 'C', 'slug' => 'c-price', 'description' => 'C', 'parent_id' => $parent->id,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function createPayload(array $overrides = []): array
    {
        return array_merge([
            'store_id' => $this->store->id,
            'product_category_id' => $this->category->id,
            'name' => 'Produk',
            'description' => 'desc',
            'price' => 10000,
            'stock' => 5,
            'weight' => 100,
            'condition' => 'new',
            'product_images' => [
                ['image' => UploadedFile::fake()->image('p.jpg'), 'is_thumbnail' => true],
            ],
        ], $overrides);
    }

    public function test_integer_price_is_accepted(): void
    {
        $this->actingAs($this->seller, 'sanctum')
            ->postJson('/api/product', $this->createPayload(['price' => 12_345]))
            ->assertStatus(201);
    }

    public function test_integer_string_price_is_accepted(): void
    {
        // Laravel's `integer` rule validates the value, not the PHP type.
        $this->actingAs($this->seller, 'sanctum')
            ->postJson('/api/product', $this->createPayload(['price' => '12345']))
            ->assertStatus(201);
    }

    public function test_fractional_price_is_rejected_on_create(): void
    {
        $this->actingAs($this->seller, 'sanctum')
            ->postJson('/api/product', $this->createPayload(['price' => 10000.50]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('price');
    }

    public function test_fractional_variant_price_is_rejected_on_create(): void
    {
        $payload = $this->createPayload([
            'variants' => [
                ['name' => 'M', 'price' => 10000.50, 'stock' => 3],
            ],
        ]);

        $this->actingAs($this->seller, 'sanctum')
            ->postJson('/api/product', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('variants.0.price');
    }

    public function test_fractional_price_is_rejected_on_update(): void
    {
        $product = Product::create([
            'store_id' => $this->store->id,
            'product_category_id' => $this->category->id,
            'name' => 'P', 'slug' => 'p-update-price',
            'description' => 'd', 'condition' => 'new',
            'price' => 10000, 'weight' => 100, 'stock' => 5,
        ]);

        $this->actingAs($this->seller, 'sanctum')
            ->putJson("/api/product/{$product->id}", [
                'store_id' => $this->store->id,
                'product_category_id' => $this->category->id,
                'name' => 'P', 'description' => 'd', 'condition' => 'new',
                'price' => 10000.50, 'weight' => 100, 'stock' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('price');
    }
}
