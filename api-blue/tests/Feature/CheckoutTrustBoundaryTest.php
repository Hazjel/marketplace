<?php

namespace Tests\Feature;

use App\Interfaces\ShippingGatewayInterface;
use App\Models\Buyer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakeShippingGateway;
use Tests\TestCase;

/**
 * Checkout dulu memakai buyer_id dan store_id apa adanya dari payload, jadi
 * pembeli yang sudah login bisa memesan atas nama orang lain, atau menempelkan
 * pesanannya ke toko yang tidak menjual produk itu.
 */
class CheckoutTrustBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private User $attacker;

    private Buyer $victimBuyer;

    private Store $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->attacker = User::factory()->create();
        $this->attacker->assignRole('buyer');
        Buyer::factory()->create(['user_id' => $this->attacker->id]);

        $victimUser = User::factory()->create();
        $victimUser->assignRole('buyer');
        $this->victimBuyer = Buyer::factory()->create(['user_id' => $victimUser->id]);

        $category = ProductCategory::create([
            'name' => 'General', 'slug' => 'general-trust', 'description' => 'General',
        ]);

        $sellerUser = User::factory()->create();
        $sellerUser->assignRole('store');
        $this->store = Store::factory()->create(['user_id' => $sellerUser->id]);

        $this->product = Product::create([
            'store_id' => $this->store->id,
            'product_category_id' => $category->id,
            'name' => 'Barang Uji',
            'slug' => 'barang-uji',
            'description' => 'Deskripsi',
            'condition' => 'new',
            'price' => 100000,
            'weight' => 1000,
            'stock' => 10,
        ]);
        // Checkout menanyakan ongkir ke gateway; jangan sentuh Komerce asli.
        $this->app->bind(ShippingGatewayInterface::class, fn () => new FakeShippingGateway);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'address_id' => 1,
            'address' => 'Jl. Uji',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'shipping' => 'JNE',
            'shipping_type' => 'REG',
            'shipping_cost' => 10000,
            'products' => [
                ['product_id' => $this->product->id, 'qty' => 1],
            ],
        ], $overrides);
    }

    private function checkout(array $payload)
    {
        return $this->actingAs($this->attacker, 'sanctum')
            ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/transaction', $payload);
    }

    public function test_buyer_id_from_the_payload_is_ignored(): void
    {
        $response = $this->checkout($this->payload([
            'buyer_id' => $this->victimBuyer->id,
            'store_id' => $this->store->id,
        ]));

        $response->assertStatus(201);

        $transaction = Transaction::firstOrFail();
        $this->assertSame(
            $this->attacker->buyer->id,
            $transaction->buyer_id,
            'transaksi harus tercatat atas pembeli yang sedang login'
        );
        $this->assertNotSame($this->victimBuyer->id, $transaction->buyer_id);
    }

    public function test_store_id_is_derived_from_the_products(): void
    {
        $otherSeller = User::factory()->create();
        $otherSeller->assignRole('store');
        $otherStore = Store::factory()->create(['user_id' => $otherSeller->id]);

        $response = $this->checkout($this->payload([
            'store_id' => $otherStore->id,
        ]));

        $response->assertStatus(201);

        $transaction = Transaction::firstOrFail();
        $this->assertSame(
            $this->store->id,
            $transaction->store_id,
            'toko harus berasal dari produk, bukan dari payload'
        );
    }

    public function test_checkout_works_without_buyer_id_and_store_id(): void
    {
        // Web dan mobile harus bisa berhenti mengirim kedua field itu tanpa
        // menunggu rilis backend berikutnya.
        $this->checkout($this->payload())->assertStatus(201);

        $this->assertSame($this->attacker->buyer->id, Transaction::firstOrFail()->buyer_id);
    }

    public function test_shipping_cost_from_the_payload_is_ignored(): void
    {
        // Gateway (di-fake) memberi harga 15000 untuk JNE REG.
        $this->checkout($this->payload(['shipping_cost' => 0]))->assertStatus(201);

        $this->assertSame(15000, (int) Transaction::firstOrFail()->shipping_cost);
    }

    public function test_an_inflated_shipping_cost_is_also_overridden(): void
    {
        // Bukan hanya nol: nilai apa pun dari client tidak dipakai, supaya
        // penjual juga tidak bisa ditagihkan ongkir yang dikarang.
        $this->checkout($this->payload(['shipping_cost' => 999999]))->assertStatus(201);

        $this->assertSame(15000, (int) Transaction::firstOrFail()->shipping_cost);
    }

    public function test_checkout_works_without_shipping_cost_at_all(): void
    {
        $payload = $this->payload();
        unset($payload['shipping_cost']);

        $this->checkout($payload)->assertStatus(201);

        $this->assertSame(15000, (int) Transaction::firstOrFail()->shipping_cost);
    }

    public function test_a_courier_the_gateway_does_not_offer_is_rejected(): void
    {
        // Kalau opsinya tidak ada di gateway, harganya tidak bisa
        // dipertanggungjawabkan -- jangan diam-diam memakai angka client.
        $this->checkout($this->payload([
            'shipping' => 'KURIR-PALSU',
            'shipping_type' => 'INSTAN',
            'shipping_cost' => 1,
        ]))->assertStatus(500);

        $this->assertSame(0, Transaction::count());
    }

    public function test_products_from_two_different_stores_are_rejected(): void
    {
        $otherSeller = User::factory()->create();
        $otherSeller->assignRole('store');
        $otherStore = Store::factory()->create(['user_id' => $otherSeller->id]);

        $foreign = Product::create([
            'store_id' => $otherStore->id,
            'product_category_id' => $this->product->product_category_id,
            'name' => 'Barang Toko Lain',
            'slug' => 'barang-toko-lain',
            'description' => 'Deskripsi',
            'condition' => 'new',
            'price' => 50000,
            'weight' => 500,
            'stock' => 10,
        ]);

        $this->checkout($this->payload([
            'products' => [
                ['product_id' => $this->product->id, 'qty' => 1],
                ['product_id' => $foreign->id, 'qty' => 1],
            ],
        ]))->assertStatus(500);

        $this->assertSame(0, Transaction::count());
        $this->assertSame(10, $this->product->fresh()->stock, 'stok tidak boleh terpotong');
    }
}
