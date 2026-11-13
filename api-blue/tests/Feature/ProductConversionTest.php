<?php

namespace Tests\Feature;

use App\Interfaces\PaymentGatewayInterface;
use App\Interfaces\ShippingGatewayInterface;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariantMongo;
use App\Models\ProductView;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakePaymentGateway;
use Tests\Support\FakeShippingGateway;
use Tests\TestCase;

/**
 * "Paket konversi": harga coret (compare_at_price, display only), the
 * "Terlaris" flag, and the signed-in buyer's recently viewed products.
 */
class ProductConversionTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Store $store;

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->app->bind(ShippingGatewayInterface::class, fn () => new FakeShippingGateway);
        $this->app->bind(PaymentGatewayInterface::class, fn () => new FakePaymentGateway);

        $this->seller = User::factory()->create();
        $this->seller->assignRole('store');
        $this->store = Store::factory()->create(['user_id' => $this->seller->id, 'is_active' => true]);

        $parent = ProductCategory::create(['name' => 'P', 'slug' => 'p-conv', 'description' => 'P']);
        $this->category = ProductCategory::create([
            'name' => 'C', 'slug' => 'c-conv', 'description' => 'C', 'parent_id' => $parent->id,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'store_id' => $this->store->id,
            'product_category_id' => $this->category->id,
            'name' => 'Produk',
            'description' => 'desc',
            'price' => 100_000,
            'stock' => 5,
            'weight' => 1,
            'condition' => 'new',
            'product_images' => [
                ['image' => UploadedFile::fake()->image('p.jpg'), 'is_thumbnail' => true],
            ],
        ], $overrides);
    }

    /** @param array<string, mixed> $attributes */
    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'store_id' => $this->store->id,
            'product_category_id' => $this->category->id,
            'price' => 100_000,
        ], $attributes));
    }

    private function createProduct(array $overrides)
    {
        return $this->actingAs($this->seller, 'sanctum')->postJson('/api/product', $this->payload($overrides));
    }

    // ---- harga coret: validation -------------------------------------

    public function test_compare_at_price_must_be_above_price(): void
    {
        foreach ([100_000, 90_000] as $compareAt) {
            $this->createProduct(['compare_at_price' => $compareAt])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['compare_at_price' => 'lebih tinggi dari harga jual']);
        }
    }

    public function test_discount_above_ninety_percent_is_rejected(): void
    {
        // 100_000 off 1_000_001 is a hair over 90%.
        $this->createProduct(['compare_at_price' => 1_000_001])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['compare_at_price' => 'Diskon maksimal 90%']);
    }

    public function test_exactly_ninety_percent_and_empty_are_accepted(): void
    {
        $this->createProduct(['compare_at_price' => 1_000_000])
            ->assertStatus(201)
            ->assertJsonPath('data.compare_at_price', 1_000_000)
            ->assertJsonPath('data.discount_percent', 90);

        $this->createProduct(['compare_at_price' => null])
            ->assertStatus(201)
            ->assertJsonPath('data.compare_at_price', null)
            ->assertJsonPath('data.discount_percent', null);
    }

    public function test_variant_compare_at_price_is_checked_against_its_own_price(): void
    {
        $this->createProduct([
            'variants' => [
                ['name' => 'A', 'price' => 50_000, 'compare_at_price' => 60_000, 'stock' => 1],
                ['name' => 'B', 'price' => 80_000, 'compare_at_price' => 70_000, 'stock' => 1],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['variants.1.compare_at_price'])
            ->assertJsonMissingValidationErrors(['variants.0.compare_at_price']);
    }

    public function test_update_without_the_field_keeps_it_and_null_clears_it(): void
    {
        $product = $this->product(['compare_at_price' => 150_000]);
        $base = [
            'product_category_id' => $this->category->id, 'name' => $product->name,
            'description' => 'd', 'condition' => 'new', 'price' => 120_000,
            'weight' => 1, 'stock' => 3,
        ];

        // An older client (the mobile app) does not know the field.
        $this->actingAs($this->seller, 'sanctum')->putJson("/api/product/{$product->id}", $base)
            ->assertOk()
            ->assertJsonPath('data.compare_at_price', 150_000)
            ->assertJsonPath('data.discount_percent', 20);

        $this->actingAs($this->seller, 'sanctum')
            ->putJson("/api/product/{$product->id}", $base + ['compare_at_price' => null])
            ->assertOk()
            ->assertJsonPath('data.compare_at_price', null);
    }

    // ---- harga coret: resource ----------------------------------------

    public function test_detail_and_list_expose_compare_at_price_and_floored_discount(): void
    {
        // 1 - 100_000/149_999 = 33.33% -> 33
        $product = $this->product(['compare_at_price' => 149_999]);

        $this->getJson("/api/product/slug/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.price', 100_000)
            ->assertJsonPath('data.compare_at_price', 149_999)
            ->assertJsonPath('data.discount_percent', 33);

        $this->getJson('/api/product')
            ->assertOk()
            ->assertJsonPath('data.0.compare_at_price', 149_999)
            ->assertJsonPath('data.0.discount_percent', 33);
    }

    public function test_a_stale_compare_at_price_not_above_price_is_not_shown(): void
    {
        $product = $this->product(['compare_at_price' => 100_000]);

        $this->getJson("/api/product/slug/{$product->slug}")
            ->assertJsonPath('data.compare_at_price', null)
            ->assertJsonPath('data.discount_percent', null);
    }

    public function test_variant_product_card_uses_the_cheapest_variant_discount(): void
    {
        $id = $this->createProduct([
            'variants' => [
                ['name' => 'Besar', 'price' => 80_000, 'compare_at_price' => 160_000, 'stock' => 1],
                ['name' => 'Kecil', 'price' => 50_000, 'compare_at_price' => 100_000, 'stock' => 1],
                ['name' => 'Polos', 'price' => 50_000, 'stock' => 1],
            ],
        ])->assertStatus(201)->json('data.id');

        $response = $this->getJson("/api/product/{$id}")
            ->assertOk()
            ->assertJsonPath('data.price', 50_000)
            ->assertJsonPath('data.compare_at_price', 100_000)
            ->assertJsonPath('data.discount_percent', 50);

        $variants = collect($response->json('data.variants'))->keyBy('name');
        $this->assertSame(160_000, $variants['Besar']['compare_at_price']);
        $this->assertSame(50, $variants['Besar']['discount_percent']);
        $this->assertNull($variants['Polos']['compare_at_price']);
        $this->assertNull($variants['Polos']['discount_percent']);

        // Clear Kecil's, leave Besar's field out: the card loses its discount, Besar keeps its own.
        $this->actingAs($this->seller, 'sanctum')->putJson("/api/product/{$id}", [
            'product_category_id' => $this->category->id, 'name' => 'Produk', 'description' => 'd',
            'condition' => 'new', 'price' => 50_000, 'weight' => 1, 'stock' => 3,
            'variants' => [
                ['id' => $variants['Besar']['id'], 'name' => 'Besar', 'price' => 80_000, 'stock' => 1],
                ['id' => $variants['Kecil']['id'], 'name' => 'Kecil', 'price' => 50_000, 'compare_at_price' => null, 'stock' => 1],
                ['id' => $variants['Polos']['id'], 'name' => 'Polos', 'price' => 50_000, 'stock' => 1],
            ],
        ])->assertOk()->assertJsonPath('data.compare_at_price', null);

        $variants = collect($this->getJson("/api/product/{$id}")->json('data.variants'))->keyBy('name');
        $this->assertSame(160_000, $variants['Besar']['compare_at_price']);
        $this->assertNull($variants['Kecil']['compare_at_price']);

        ProductVariantMongo::where('product_id', $id)->delete();
    }

    // ---- harga coret: money ---------------------------------------------

    public function test_checkout_charges_price_and_ignores_compare_at_price(): void
    {
        $this->store->storeBalance()->create(['balance' => 0]);
        $product = $this->product(['price' => 100_000, 'compare_at_price' => 200_000, 'stock' => 10]);

        $buyer = User::factory()->create();
        $buyer->assignRole('buyer');
        $buyer->buyer()->create(['phone_number' => '0898', 'city' => 'Bandung', 'address' => 'Jl. B']);

        $this->actingAs($buyer, 'sanctum')
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/transaction', [
                'address_id' => 101, 'address' => 'Jl. Kirim', 'city' => 'Surabaya',
                'postal_code' => '60000', 'shipping' => 'JNE', 'shipping_type' => 'REG',
                'products' => [['product_id' => $product->id, 'qty' => 2]],
            ])
            ->assertStatus(201);

        // 2 x price + shipping 15_000 (fake) + service fee 1_000
        $this->assertDatabaseHas('transactions', ['store_id' => $this->store->id, 'grand_total' => 216_000]);
        $this->assertDatabaseHas('transaction_details', ['product_id' => $product->id, 'subtotal' => 200_000]);
    }

    // ---- Terlaris -------------------------------------------------------------

    private function sell(Product $product, int $qty, string $status = 'paid', ?int $daysAgo = 1): void
    {
        // make()+save(): the factory's afterCreating adds random detail rows.
        $transaction = Transaction::factory()->make([
            'store_id' => $this->store->id,
            'payment_status' => $status,
            'created_at' => now()->subDays($daysAgo),
        ]);
        $transaction->save();
        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'qty' => $qty,
            'subtotal' => 100_000 * $qty,
        ]);
    }

    private function isBestSeller(Product $product): bool
    {
        return $this->getJson("/api/product/slug/{$product->slug}")->assertOk()->json('data.is_best_seller');
    }

    public function test_best_seller_needs_ten_paid_units_in_thirty_days(): void
    {
        $nine = $this->product();
        $this->sell($nine, 5);
        $this->sell($nine, 4);
        $this->sell($nine, 50, 'unpaid');
        $this->sell($nine, 50, 'paid', Product::BEST_SELLER_DAYS + 1);

        $ten = $this->product();
        $this->sell($ten, 6);
        $this->sell($ten, 4, 'paid', Product::BEST_SELLER_DAYS - 1);

        $this->assertFalse($this->isBestSeller($nine));
        $this->assertTrue($this->isBestSeller($ten));

        $list = collect($this->getJson('/api/product')->assertOk()->json('data'))->keyBy('id');
        $this->assertFalse($list[$nine->id]['is_best_seller']);
        $this->assertTrue($list[$ten->id]['is_best_seller']);
    }

    public function test_listing_does_not_query_per_product_for_the_flag(): void
    {
        $this->product();
        $this->getJson('/api/product'); // warm up (permission cache etc.)

        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/product')->assertOk();

            return count(DB::getQueryLog());
        };

        $one = $count();
        foreach (range(1, 4) as $_) {
            $this->sell($this->product(), 10);
        }
        $this->assertSame($one, $count(), json_encode(array_column(DB::getQueryLog(), 'query'), JSON_PRETTY_PRINT));
    }

    // ---- terakhir dilihat -------------------------------------------------------

    private function viewed(User $user, Product $product, int $minutesAgo): void
    {
        ProductView::create([
            'user_id' => $user->id, 'product_id' => $product->id, 'viewed_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    public function test_recently_viewed_is_distinct_newest_first_and_capped_at_ten(): void
    {
        $user = User::factory()->create();
        $products = collect(range(1, 12))->map(fn () => $this->product());

        foreach ($products as $i => $product) {
            $this->viewed($user, $product, 1000 - $i); // later index = more recent
        }
        // Seen again just now: comes first, and only once.
        $this->viewed($user, $products[0], 0);

        $ids = collect($this->actingAs($user, 'sanctum')->getJson('/api/product/recently-viewed')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'price', 'compare_at_price', 'discount_percent', 'is_best_seller', 'store' => ['is_verified']]]])
            ->json('data'))->pluck('id');

        $this->assertCount(10, $ids);
        $this->assertSame($products[0]->id, $ids[0]);
        $this->assertSame($ids->unique()->count(), $ids->count());
        $this->assertSame([$products[11]->id, $products[10]->id], [$ids[1], $ids[2]]);
    }

    public function test_recently_viewed_is_scoped_to_the_user_and_skips_inactive_stores(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $mine = $this->product();
        $bobs = $this->product();

        $closedStore = Store::factory()->create(['is_active' => false]);
        $closed = $this->product(['store_id' => $closedStore->id]);

        $this->viewed($alice, $mine, 5);
        $this->viewed($alice, $closed, 1);
        $this->viewed($bob, $bobs, 1);
        ProductView::create(['session_id' => 'guest-1', 'product_id' => $bobs->id, 'viewed_at' => now()]);

        $ids = collect($this->actingAs($alice, 'sanctum')->getJson('/api/product/recently-viewed')
            ->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
    }

    public function test_recently_viewed_requires_login(): void
    {
        $this->getJson('/api/product/recently-viewed')->assertUnauthorized();
    }

    // ---- review fixes ---------------------------------------------------------

    public function test_best_seller_counts_an_order_by_when_it_was_paid(): void
    {
        $product = $this->product();
        // Ordered just before the window, paid inside it.
        $this->sell($product, 10, 'paid', Product::BEST_SELLER_DAYS + 1);
        Transaction::query()->update(['paid_at' => now()->subDays(Product::BEST_SELLER_DAYS - 1)]);

        $this->assertTrue($this->isBestSeller($product));
    }

    public function test_best_seller_is_one_grouped_aggregate_not_a_subquery_per_product(): void
    {
        $this->product();

        DB::enableQueryLog();
        $this->getJson('/api/product')->assertOk();
        $sql = collect(DB::getQueryLog())->pluck('query')->first(fn ($q) => str_contains($q, 'recent_sold_qty'));

        $this->assertNotNull($sql);
        $this->assertStringContainsString('left join (select', $sql);
        $this->assertStringContainsString('group by "transaction_details"."product_id"', $sql);
        $this->assertStringContainsString('COALESCE(recent_sales.qty, 0) as recent_sold_qty', $sql);
    }

    /** @return array<string, mixed> */
    private function updateBase(Product $product, array $overrides): array
    {
        return array_merge([
            'product_category_id' => $this->category->id, 'name' => $product->name,
            'description' => 'd', 'condition' => 'new', 'weight' => 1, 'stock' => 3,
        ], $overrides);
    }

    public function test_update_checks_a_new_price_against_the_stored_compare_at_price(): void
    {
        $product = $this->product(['price' => 10_000, 'compare_at_price' => 100_000]); // 90%
        $put = fn (array $data) => $this->actingAs($this->seller, 'sanctum')
            ->putJson("/api/product/{$product->id}", $this->updateBase($product, $data));

        $put(['price' => 5_000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['compare_at_price' => 'Diskon maksimal 90%']);
        $put(['price' => 100_000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['compare_at_price' => 'kosongkan']);

        $put(['price' => 12_000])->assertOk()->assertJsonPath('data.compare_at_price', 100_000);
        $put(['price' => 5_000, 'compare_at_price' => null])->assertOk()->assertJsonPath('data.compare_at_price', null);
    }

    public function test_update_checks_a_new_variant_price_against_its_stored_compare_at_price(): void
    {
        $id = $this->createProduct([
            'variants' => [['name' => 'Kecil', 'price' => 10_000, 'compare_at_price' => 100_000, 'stock' => 1]],
        ])->assertStatus(201)->json('data.id');
        $variantId = (string) ProductVariantMongo::where('product_id', $id)->first()->id;
        $product = Product::find($id);
        $put = fn (int $price) => $this->actingAs($this->seller, 'sanctum')
            ->putJson("/api/product/{$id}", $this->updateBase($product, [
                'price' => $price,
                'variants' => [['id' => $variantId, 'name' => 'Kecil', 'price' => $price, 'stock' => 1]],
            ]));

        $put(5_000)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['variants.0.compare_at_price' => 'Diskon maksimal 90%']);
        $put(100_000)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['variants.0.compare_at_price' => 'kosongkan']);
        $put(20_000)->assertOk();
        $this->assertSame('100000.00', (string) ProductVariantMongo::find($variantId)->compare_at_price);

        ProductVariantMongo::where('product_id', $id)->delete();
    }

    public function test_prices_are_capped_so_the_discount_math_cannot_overflow(): void
    {
        $max = Product::MAX_PRICE;

        $this->createProduct(['price' => $max + 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price' => 'maksimal']);
        $this->createProduct(['price' => $max - 1, 'compare_at_price' => $max + 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['compare_at_price' => 'maksimal']);

        $this->createProduct(['price' => $max / 10, 'compare_at_price' => $max])
            ->assertStatus(201)
            ->assertJsonPath('data.discount_percent', 90);

        // Rows written past the API (or before the cap) still serialize.
        $this->assertSame(90, Product::discountPercent(10 ** 17, 10 ** 18));
        $this->assertSame(1, Product::discountPercent(PHP_INT_MAX - 10 ** 17, PHP_INT_MAX));
    }

    public function test_recently_viewed_is_throttled(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 60) as $_) {
            $this->actingAs($user, 'sanctum')->getJson('/api/product/recently-viewed')->assertOk();
        }
        $this->actingAs($user, 'sanctum')->getJson('/api/product/recently-viewed')->assertStatus(429);
    }
}
