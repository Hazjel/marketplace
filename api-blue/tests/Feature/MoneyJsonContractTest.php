<?php

namespace Tests\Feature;

use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductVariantResource;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\VoucherResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariantMongo;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Sprint C1 — the money JSON contract. After B3.2 every rupiah amount is
 * whole, and the API must emit it as an integer, not a `decimal:2` string
 * ("150000.00") or a float. A client doing int.tryParse() must get the
 * value.
 *
 * Exceptions (still decimal, documented in docs/money-json-contract.md):
 * a percentage voucher's `value` (a rate, not rupiah); the escrow ledger
 * (`store_balances`, histories, `withdrawals`) and `weight`.
 */
class MoneyJsonContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function category(): ProductCategory
    {
        return ProductCategory::firstOrCreate(
            ['slug' => 'c1'],
            ['name' => 'C1', 'description' => 'fixture']
        );
    }

    public function test_product_and_variant_price_are_integers(): void
    {
        $store = Store::factory()->create();
        $product = Product::create([
            'store_id' => $store->id, 'product_category_id' => $this->category()->id,
            'name' => 'P', 'slug' => 'p-c1', 'description' => 'd', 'condition' => 'new',
            'has_variants' => true, 'price' => 150_000, 'stock' => 10, 'weight' => 2.2,
        ]);
        $variant = ProductVariantMongo::create([
            'product_id' => $product->id, 'name' => 'V', 'variant_attributes' => [],
            'price' => 175_000, 'stock' => 5, 'sku' => 'C1-V',
        ]);

        $product->setRelation('variants', collect([$variant]));
        $p = (new ProductResource($product))->toArray(request());
        $this->assertIsInt($p['price']);
        $this->assertSame(150_000, $p['price']);
        // weight is not money
        $this->assertIsNotInt($p['weight']);

        $v = (new ProductVariantResource($variant))->toArray(request());
        $this->assertIsInt($v['price']);
        $this->assertSame(175_000, $v['price']);
    }

    public function test_transaction_money_fields_are_integers(): void
    {
        $this->category();
        $transaction = Transaction::factory()->create(['payment_status' => 'paid']);
        $transaction->forceFill([
            'shipping_cost' => 15_000, 'tax' => 11_006,
            'discount_amount' => 20_000, 'grand_total' => 100_050,
        ])->save();

        $t = (new TransactionResource($transaction->fresh()->load('transactionDetails')))->toArray(request());

        foreach (['shipping_cost', 'tax', 'grand_total', 'discount_amount'] as $key) {
            $this->assertIsInt($t[$key], "$key must be an integer");
        }
        $this->assertSame(11_006, $t['tax']);
        $this->assertSame(100_050, $t['grand_total']);

        // nested transaction_details[].subtotal integer emission is pinned
        // by TransactionDetailSubtotalMoneyTest (B3.1).

        // raw wire form of the resolved response: no ".0", no decimal string
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $json = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/transaction/{$transaction->id}")
            ->assertStatus(200)
            ->getContent();
        $this->assertMatchesRegularExpression('/"tax":11006,/', $json);
        $this->assertStringNotContainsString('"grand_total":"', $json);
    }

    public function test_voucher_rupiah_fields_are_integers_percentage_value_is_a_rate(): void
    {
        $fixed = Voucher::create([
            'code' => 'FIX', 'store_id' => null, 'type' => 'fixed',
            'value' => 20_000, 'min_purchase' => 50_000, 'max_discount' => null, 'is_active' => true,
        ]);
        $pct = Voucher::create([
            'code' => 'PCT', 'store_id' => null, 'type' => 'percentage',
            'value' => 10.5, 'max_discount' => 5_000, 'is_active' => true,
        ]);

        $f = (new VoucherResource($fixed))->toArray(request());
        $this->assertIsInt($f['value']);
        $this->assertSame(20_000, $f['value']);
        $this->assertIsInt($f['min_purchase']);
        $this->assertSame(50_000, $f['min_purchase']);
        $this->assertNull($f['max_discount']);

        $p = (new VoucherResource($pct))->toArray(request());
        // a percentage rate keeps its decimals — NOT narrowed to int
        $this->assertEqualsWithDelta(10.5, $p['value'], 0.001);
        $this->assertIsInt($p['max_discount']);
        $this->assertSame(5_000, $p['max_discount']);
    }

    public function test_voucher_validate_endpoint_emits_integer_discount(): void
    {
        $store = Store::factory()->create();
        $buyerUser = User::factory()->create();
        $buyerUser->assignRole('buyer');
        $buyerUser->buyer()->create(['phone_number' => '08', 'city' => 'B', 'address' => 'A']);

        Voucher::create([
            'code' => 'HEMAT', 'store_id' => null, 'type' => 'percentage',
            'value' => 10, 'max_discount' => 5_000, 'is_active' => true,
        ]);

        $response = $this->actingAs($buyerUser, 'sanctum')->postJson('/api/voucher/validate', [
            'code' => 'HEMAT', 'store_id' => $store->id, 'subtotal' => 100_000,
        ])->assertStatus(200);

        $this->assertIsInt($response->json('data.discount_amount'));
        $this->assertSame(5_000, $response->json('data.discount_amount'));
    }

    public function test_health_endpoint_shape(): void
    {
        $response = $this->getJson('/api/health')->assertStatus(200);

        $response->assertJsonStructure(['status', 'timestamp', 'version', 'services' => ['database', 'cache']]);
        // APP_VERSION is unset in the test env -> "undefined", never a stale number
        $this->assertSame('undefined', $response->json('version'));
    }
}
