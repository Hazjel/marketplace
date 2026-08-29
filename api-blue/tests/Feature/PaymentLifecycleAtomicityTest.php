<?php

namespace Tests\Feature;

use App\Interfaces\EscrowRepositoryInterface;
use App\Interfaces\TransactionRepositoryInterface;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * checkPaymentStatus() (manual), complete() (buyer), dan dua scheduler
 * (transaction:auto-complete, transaction:check-expiry) dulu masing-masing
 * membaca-memutuskan-menyimpan tanpa DB::transaction() atau lockForUpdate()
 * -- hanya webhook Midtrans yang atomic. EscrowRepository::credit()/
 * release()/refund() juga dulu memutasi saldo SEBELUM menulis baris
 * unique_ref, sebagai dua statement lepas: kalau caller tidak membungkusnya
 * sendiri, insert kedua yang gagal karena unique_ref tidak membatalkan
 * mutasi saldo yang sudah ter-commit di statement pertama.
 */
class PaymentLifecycleAtomicityTest extends TestCase
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
            'name' => 'General', 'slug' => 'general-lifecycle', 'description' => 'General',
        ]);
        $this->product = Product::create([
            'store_id' => $this->store->id,
            'product_category_id' => $category->id,
            'name' => 'Barang Uji Lifecycle',
            'slug' => 'barang-uji-lifecycle',
            'description' => 'Deskripsi',
            'condition' => 'new',
            'price' => 100000,
            'weight' => 1000,
            'stock' => 10,
        ]);
    }

    private function makeTransaction(string $code, string $paymentStatus, string $deliveryStatus = 'processing'): Transaction
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
            'payment_status' => $paymentStatus,
            'delivery_status' => $deliveryStatus,
        ]);
        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->product->id,
            'qty' => 2,
            'subtotal' => 200000,
        ]);

        return $transaction;
    }

    private function mockMidtransStatus(string $transactionStatus): void
    {
        $mock = Mockery::mock('alias:Midtrans\Transaction');
        $mock->shouldReceive('status')->andReturn((object) [
            'transaction_status' => $transactionStatus,
            'payment_type' => 'bank_transfer',
            'fraud_status' => null,
        ]);
    }

    // ---- restoreStock: exactly-once ----

    public function test_restore_stock_called_twice_only_restores_once(): void
    {
        $transaction = $this->makeTransaction('LIFE_001', 'failed');
        $this->product->decrement('stock', 2); // simulasikan stok sudah terpotong saat checkout
        $stockAfterCheckout = $this->product->fresh()->stock;

        $repo = app(TransactionRepositoryInterface::class);
        $mongoAdjustments = [];
        $repo->restoreStock($transaction->fresh(), $mongoAdjustments);
        $repo->restoreStock($transaction->fresh(), $mongoAdjustments);

        $this->assertSame($stockAfterCheckout + 2, $this->product->fresh()->stock);
    }

    public function test_restore_stock_sets_the_marker(): void
    {
        $transaction = $this->makeTransaction('LIFE_002', 'failed');

        $mongoAdjustments = [];
        app(TransactionRepositoryInterface::class)->restoreStock($transaction, $mongoAdjustments);

        $this->assertNotNull($transaction->fresh()->stock_restored_at);
    }

    // ---- checkPaymentStatus: atomic credit ----

    public function test_check_payment_status_repeated_call_credits_once(): void
    {
        $transaction = $this->makeTransaction('LIFE_003', 'unpaid');
        $this->mockMidtransStatus('settlement');

        $this->actingAs($this->buyer->user, 'sanctum')
            ->postJson("/api/transaction/{$transaction->id}/check-status")
            ->assertStatus(200);
        $afterFirst = $this->storeBalance->fresh()->pending_balance;

        $this->actingAs($this->buyer->user, 'sanctum')
            ->postJson("/api/transaction/{$transaction->id}/check-status")
            ->assertStatus(200);
        $afterSecond = $this->storeBalance->fresh()->pending_balance;

        $this->assertGreaterThan(0, (float) $afterFirst);
        $this->assertSame((float) $afterFirst, (float) $afterSecond);
        $this->assertSame(1, DB::table('store_balance_histories')
            ->where('reference_id', $transaction->id)
            ->where('type', 'pending_income')
            ->count());
    }

    public function test_check_payment_status_cannot_move_a_paid_transaction_backward(): void
    {
        $transaction = $this->makeTransaction('LIFE_004', 'paid');
        $this->mockMidtransStatus('deny');

        $this->actingAs($this->buyer->user, 'sanctum')
            ->postJson("/api/transaction/{$transaction->id}/check-status")
            ->assertStatus(200);

        $this->assertSame('paid', $transaction->fresh()->payment_status);
    }

    // ---- complete(): atomic release, exactly-once ----

    public function test_complete_releases_escrow_exactly_once_across_repeated_calls(): void
    {
        $transaction = $this->makeTransaction('LIFE_005', 'paid', 'delivering');
        // credit() beneran, bukan angka pending_balance yang dikarang --
        // supaya admin_fee dan pending_balance konsisten seperti alur nyata.
        app(EscrowRepositoryInterface::class)->credit($transaction);

        $response = $this->actingAs($this->buyer->user, 'sanctum')
            ->postJson("/api/transaction/{$transaction->id}/complete", [
                'receiving_proof' => UploadedFile::fake()->image('proof.jpg'),
            ]);
        $response->assertStatus(200);

        // Panggilan kedua: delivery_status sudah 'completed', bukan lagi
        // 'delivering', jadi pengecekan ulang DI DALAM lock harus menolaknya.
        $second = $this->actingAs($this->buyer->user, 'sanctum')
            ->postJson("/api/transaction/{$transaction->id}/complete", [
                'receiving_proof' => UploadedFile::fake()->image('proof2.jpg'),
            ]);
        $second->assertStatus(400);

        $this->assertSame(1, DB::table('store_balance_histories')
            ->where('reference_id', $transaction->id)
            ->where('type', 'released')
            ->count());
    }

    public function test_complete_transaction_repository_rechecks_status_under_lock(): void
    {
        // Controller punya pre-check delivery_status sebelum masuk lock, dan
        // scheduler memfilter query-nya sebelum memanggil ulang -- keduanya
        // bisa menutupi kalau pengecekan ulang DI DALAM lock repository ini
        // sendiri hilang. Panggil repository-nya langsung dua kali supaya
        // guard di dalam lock ini betul-betul teruji sendirian.
        $transaction = $this->makeTransaction('LIFE_009', 'paid', 'delivering');
        app(EscrowRepositoryInterface::class)->credit($transaction);

        $repo = app(TransactionRepositoryInterface::class);
        $repo->completeTransaction($transaction->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Hanya status delivering yang bisa diselesaikan');
        $repo->completeTransaction($transaction->id);
    }

    // ---- Scheduler: transaction:auto-complete ----

    public function test_auto_complete_scheduler_releases_escrow_exactly_once(): void
    {
        $transaction = $this->makeTransaction('LIFE_006', 'paid', 'delivering');
        app(EscrowRepositoryInterface::class)->credit($transaction);
        DB::table('transactions')->where('id', $transaction->id)
            ->update(['updated_at' => now()->subDays(8)]);

        $this->artisan('transaction:auto-complete');
        $this->artisan('transaction:auto-complete');

        $this->assertSame('completed', $transaction->fresh()->delivery_status);
        $this->assertSame(1, DB::table('store_balance_histories')
            ->where('reference_id', $transaction->id)
            ->where('type', 'released')
            ->count());
    }

    // ---- Scheduler: transaction:check-expiry ----

    public function test_expiry_scheduler_restores_stock_exactly_once_across_repeated_runs(): void
    {
        $transaction = $this->makeTransaction('LIFE_007', 'unpaid');
        $this->product->decrement('stock', 2);
        $stockAfterCheckout = $this->product->fresh()->stock;
        DB::table('transactions')->where('id', $transaction->id)
            ->update(['created_at' => now()->subMinutes(20)]);

        $this->artisan('transaction:check-expiry');
        $this->artisan('transaction:check-expiry');

        $this->assertSame('failed', $transaction->fresh()->payment_status);
        $this->assertSame($stockAfterCheckout + 2, $this->product->fresh()->stock);
    }

    public function test_expiry_scheduler_does_not_touch_a_transaction_already_paid(): void
    {
        // Kandidat diambil tanpa lock lewat query awal; kalau sudah dibayar
        // di antara query itu dan pemrosesan baris, scheduler tidak boleh
        // menimpanya balik jadi failed hanya karena awalnya masuk daftar.
        $transaction = $this->makeTransaction('LIFE_008', 'unpaid');
        $this->product->decrement('stock', 2);
        $stockAfterCheckout = $this->product->fresh()->stock;
        DB::table('transactions')->where('id', $transaction->id)
            ->update(['created_at' => now()->subMinutes(20)]);

        // Simulasikan webhook membayar transaksi ini tepat sebelum baris
        // scheduler diproses.
        $transaction->update(['payment_status' => 'paid']);

        $this->artisan('transaction:check-expiry');

        $this->assertSame('paid', $transaction->fresh()->payment_status);
        $this->assertSame($stockAfterCheckout, $this->product->fresh()->stock);
    }
}
