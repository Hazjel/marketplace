<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\StoreBalance;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Callback pembayaran dulu memanggil lockForUpdate() di luar transaksi mana
 * pun. Pada autocommit, lock itu lepas begitu statement selesai, jadi tidak
 * menahan apa pun: dua webhook berdekatan sama-sama membaca "unpaid" dan
 * sama-sama mengkredit saldo penjual.
 */
class PaymentCallbackAtomicityTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private StoreBalance $storeBalance;

    private Buyer $buyer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $sellerUser = User::factory()->create();
        $sellerUser->assignRole('store');
        $this->store = Store::factory()->create(['user_id' => $sellerUser->id]);
        $this->storeBalance = StoreBalance::create([
            'store_id' => $this->store->id,
            'balance' => 0,
            'pending_balance' => 0,
        ]);

        $buyerUser = User::factory()->create();
        $buyerUser->assignRole('buyer');
        $this->buyer = Buyer::factory()->create(['user_id' => $buyerUser->id]);

        $category = ProductCategory::create([
            'name' => 'General', 'slug' => 'general-atomic', 'description' => 'General',
        ]);

        $this->product = Product::create([
            'store_id' => $this->store->id,
            'product_category_id' => $category->id,
            'name' => 'Barang Uji',
            'slug' => 'barang-uji-atomic',
            'description' => 'Deskripsi',
            'condition' => 'new',
            'price' => 100000,
            'weight' => 1000,
            'stock' => 10,
        ]);
    }

    private function makeTransaction(string $code): Transaction
    {
        $transaction = Transaction::create([
            'code' => $code,
            'buyer_id' => $this->buyer->id,
            'store_id' => $this->store->id,
            'address_id' => 1,
            'address' => 'Jl. Buyer',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'shipping' => 'JNE',
            'shipping_type' => 'REG',
            'shipping_cost' => 15000,
            'tax' => 11000,
            'grand_total' => 126000,
            'payment_status' => 'unpaid',
        ]);

        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->product->id,
            'qty' => 1,
            'subtotal' => 100000,
        ]);

        return $transaction;
    }

    private function webhook(string $code, string $status)
    {
        $serverKey = config('midtrans.serverKey');

        return $this->postJson('/api/midtrans-callback', [
            'order_id' => $code,
            'status_code' => '200',
            'gross_amount' => '126000.00',
            'signature_key' => hash('sha512', $code.'200'.'126000.00'.$serverKey),
            'transaction_status' => $status,
            'payment_type' => 'bank_transfer',
        ]);
    }

    public function test_a_repeated_settlement_webhook_credits_the_seller_only_once(): void
    {
        $this->makeTransaction('ATOMIC_001');

        $this->webhook('ATOMIC_001', 'settlement')->assertStatus(200);
        $afterFirst = (float) $this->storeBalance->fresh()->pending_balance;

        $this->webhook('ATOMIC_001', 'settlement')->assertStatus(200);
        $afterSecond = (float) $this->storeBalance->fresh()->pending_balance;

        $this->assertGreaterThan(0, $afterFirst);
        $this->assertSame($afterFirst, $afterSecond, 'webhook kedua tidak boleh mengkredit lagi');
    }

    public function test_only_one_pending_income_entry_is_written_per_transaction(): void
    {
        $transaction = $this->makeTransaction('ATOMIC_002');

        $this->webhook('ATOMIC_002', 'settlement');
        $this->webhook('ATOMIC_002', 'settlement');
        $this->webhook('ATOMIC_002', 'settlement');

        $this->assertSame(1, DB::table('store_balance_histories')
            ->where('reference_id', $transaction->id)
            ->where('type', 'pending_income')
            ->count());
    }

    public function test_a_late_failure_webhook_cannot_undo_a_paid_transaction(): void
    {
        // Webhook provider tidak selalu berurutan. Kalau failed yang telat
        // diterapkan setelah pembayaran, stok dikembalikan padahal saldo
        // penjual sudah terlanjur dikredit.
        $this->makeTransaction('ATOMIC_003');

        $this->webhook('ATOMIC_003', 'settlement')->assertStatus(200);
        $stockAfterPaid = $this->product->fresh()->stock;
        $pendingAfterPaid = (float) $this->storeBalance->fresh()->pending_balance;

        $this->webhook('ATOMIC_003', 'deny')->assertStatus(200);

        $this->assertSame('paid', Transaction::where('code', 'ATOMIC_003')->first()->payment_status);
        $this->assertSame($stockAfterPaid, $this->product->fresh()->stock, 'stok tidak boleh dikembalikan');
        $this->assertSame($pendingAfterPaid, (float) $this->storeBalance->fresh()->pending_balance);
    }

    public function test_a_late_pending_webhook_cannot_reopen_a_paid_transaction(): void
    {
        $this->makeTransaction('ATOMIC_004');

        $this->webhook('ATOMIC_004', 'settlement')->assertStatus(200);
        $this->webhook('ATOMIC_004', 'pending')->assertStatus(200);

        $this->assertSame('paid', Transaction::where('code', 'ATOMIC_004')->first()->payment_status);
    }

    public function test_an_unknown_transaction_is_reported_as_not_found(): void
    {
        $this->webhook('TIDAK_ADA', 'settlement')->assertStatus(404);
    }

    public function test_a_mismatched_amount_is_refused_and_changes_nothing(): void
    {
        $this->makeTransaction('ATOMIC_005');

        $serverKey = config('midtrans.serverKey');
        $this->postJson('/api/midtrans-callback', [
            'order_id' => 'ATOMIC_005',
            'status_code' => '200',
            'gross_amount' => '1.00',
            'signature_key' => hash('sha512', 'ATOMIC_005'.'200'.'1.00'.$serverKey),
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
        ])->assertStatus(403);

        $this->assertSame('unpaid', Transaction::where('code', 'ATOMIC_005')->first()->payment_status);
        $this->assertSame(0.0, (float) $this->storeBalance->fresh()->pending_balance);
    }
}
