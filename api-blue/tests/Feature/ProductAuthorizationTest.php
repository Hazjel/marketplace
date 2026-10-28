<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariantMongo;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Store $otherStore;

    private Product $product;

    private Product $otherProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seller = User::factory()->create();
        $this->seller->assignRole('store');
        $store = Store::factory()->create(['user_id' => $this->seller->id]);
        $otherSeller = User::factory()->create();
        $otherSeller->assignRole('store');
        $this->otherStore = Store::factory()->create(['user_id' => $otherSeller->id]);
        $parent = ProductCategory::create(['name' => 'Parent', 'slug' => 'auth-parent', 'description' => 'QA']);
        $category = ProductCategory::create([
            'name' => 'Child', 'slug' => 'auth-child', 'description' => 'QA', 'parent_id' => $parent->id,
        ]);
        foreach ([$store, $this->otherStore] as $index => $owner) {
            $product = Product::create([
                'store_id' => $owner->id, 'product_category_id' => $category->id,
                'name' => 'Product '.$index, 'slug' => 'auth-product-'.$index,
                'description' => 'QA', 'condition' => 'new', 'price' => 10000, 'weight' => 1, 'stock' => 5,
            ]);
            if ($index === 0) {
                $this->product = $product;
            } else {
                $this->otherProduct = $product;
            }
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->product, $this->otherProduct)) {
            ProductVariantMongo::whereIn('product_id', [$this->product->id, $this->otherProduct->id])->delete();
        }
        parent::tearDown();
    }

    private function payload(array $changes = []): array
    {
        return array_merge($this->product->only([
            'store_id', 'product_category_id', 'name', 'description', 'condition', 'price', 'weight', 'stock',
        ]), ['price' => 10000], $changes);
    }

    private function variant(Product $product): ProductVariantMongo
    {
        $product->update(['has_variants' => true]);

        return $product->variants()->create(['name' => 'Original', 'price' => 10000, 'stock' => 5]);
    }

    public function test_seller_and_dual_role_seller_cannot_transfer_products_to_another_store(): void
    {
        foreach ([false, true] as $dualRole) {
            if ($dualRole) {
                $this->seller->assignRole('buyer');
            }
            $this->actingAs($this->seller, 'sanctum')
                ->putJson('/api/product/'.$this->product->id, $this->payload(['store_id' => $this->otherStore->id]))
                ->assertOk()->assertJsonPath('data.store.id', $this->product->store_id);
            $this->assertDatabaseHas('products', ['id' => $this->product->id, 'store_id' => $this->product->store_id]);
        }
    }

    public function test_foreign_variant_is_rejected_before_own_variants_are_deleted(): void
    {
        $own = $this->variant($this->product);
        $foreign = $this->variant($this->otherProduct);

        $this->actingAs($this->seller, 'sanctum')->putJson('/api/product/'.$this->product->id, $this->payload([
            'name' => 'Unauthorized rename',
            'variants' => [['id' => (string) $foreign->id, 'name' => 'Hijacked', 'price' => 1, 'stock' => 999]],
        ]))->assertUnprocessable()->assertJsonValidationErrors('variants.0.id');

        $this->assertSame('Original', $own->fresh()->name);
        $this->assertSame('Original', $foreign->fresh()->name);
        $this->assertSame(5, $foreign->fresh()->stock);
        $this->assertSame('Product 0', $this->product->fresh()->name);
        $this->assertEquals(10000, $this->product->fresh()->price);
    }

    public function test_unknown_and_malformed_variant_ids_do_not_delete_existing_variants(): void
    {
        $own = $this->variant($this->product);
        foreach (['000000000000000000000000', 'not-an-object-id', '$ne'] as $id) {
            $this->actingAs($this->seller, 'sanctum')->putJson('/api/product/'.$this->product->id, $this->payload([
                'variants' => [['id' => $id, 'name' => 'Unknown', 'price' => 1, 'stock' => 999]],
            ]))->assertUnprocessable()->assertJsonValidationErrors('variants.0.id');
            $this->assertSame('Original', $own->fresh()->name);
        }
    }

    public function test_owner_can_still_edit_their_own_variant(): void
    {
        $own = $this->variant($this->product);
        $this->actingAs($this->seller, 'sanctum')->putJson('/api/product/'.$this->product->id, $this->payload([
            'variants' => [['id' => (string) $own->id, 'name' => 'Updated', 'price' => 12000, 'stock' => 6]],
        ]))->assertOk()->assertJsonPath('data.price', 12000)->assertJsonPath('data.stock', 6);

        $this->assertSame('Updated', $own->fresh()->name);
        $this->assertSame(6, $own->fresh()->stock);
    }
}
