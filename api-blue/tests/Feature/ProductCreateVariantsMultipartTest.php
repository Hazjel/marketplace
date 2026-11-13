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
 * The seller web form always posts multipart (images are mandatory) and, for
 * a variant product, hides the parent price/stock inputs. ProductRepository
 * derives both from the variants, so the request must not demand them.
 */
class ProductCreateVariantsMultipartTest extends TestCase
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

        $parent = ProductCategory::create(['name' => 'P', 'slug' => 'p-mp', 'description' => 'P']);
        $this->category = ProductCategory::create([
            'name' => 'C', 'slug' => 'c-mp', 'description' => 'C', 'parent_id' => $parent->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function webFields(): array
    {
        // Same keys/shape the fixed fe-blue serializer produces; values are strings.
        return [
            'store_id' => (string) $this->store->id,
            'product_category_id' => (string) $this->category->id,
            'name' => 'Kaos Varian',
            'description' => 'desc',
            'condition' => 'new',
            'weight' => '200',
            'has_variants' => '1',
            'variants' => [
                ['name' => 'Merah/S', 'price' => '100000', 'stock' => '4', 'variant_attributes' => ['Warna' => 'Merah', 'Ukuran' => 'S']],
                ['name' => 'Biru/L', 'price' => '150000', 'stock' => '6', 'variant_attributes' => ['Warna' => 'Biru', 'Ukuran' => 'L']],
            ],
            'product_images' => [
                ['image' => UploadedFile::fake()->image('p.jpg'), 'is_thumbnail' => '1'],
            ],
        ];
    }

    public function test_variant_product_can_be_created_via_multipart_without_parent_price_and_stock(): void
    {
        $this->actingAs($this->seller, 'sanctum')
            ->post('/api/product', $this->webFields(), ['Accept' => 'application/json'])
            ->assertStatus(201);

        $product = Product::where('name', 'Kaos Varian')->firstOrFail();
        $this->assertTrue($product->has_variants);
        $this->assertSame(100000, (int) $product->price);
        $this->assertSame(10, (int) $product->stock);

        $variants = $product->variants()->get()->keyBy('name');
        $this->assertCount(2, $variants);
        $this->assertSame(['Warna' => 'Biru', 'Ukuran' => 'L'], $variants['Biru/L']->variant_attributes);
    }

    public function test_variant_product_can_be_created_via_json_without_parent_price_and_stock(): void
    {
        $fields = $this->webFields();
        unset($fields['product_images']);
        $fields['product_images'] = [['image' => UploadedFile::fake()->image('p.jpg'), 'is_thumbnail' => true]];

        $this->actingAs($this->seller, 'sanctum')
            ->postJson('/api/product', $fields)
            ->assertStatus(201);
    }

    public function test_simple_product_still_requires_price_and_stock(): void
    {
        $fields = $this->webFields();
        unset($fields['variants'], $fields['has_variants']);

        $this->actingAs($this->seller, 'sanctum')
            ->post('/api/product', $fields, ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price', 'stock']);
    }
}
