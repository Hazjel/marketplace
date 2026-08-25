<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Store;
use App\Models\StoreBalance;
use App\Models\StoreBalanceHistory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exactly-once untuk mutasi saldo dijaga oleh database, bukan hanya oleh
 * logika aplikasi. Transaksi + row lock di callback pembayaran sudah
 * mencegahnya, tetapi itu bergantung pada kode yang selalu benar; constraint
 * ini membuat kredit ganda mustahil tersimpan.
 */
class EscrowLedgerUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private StoreBalance $storeBalance;

    private Transaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $sellerUser = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $sellerUser->id]);
        $this->storeBalance = StoreBalance::create([
            'store_id' => $store->id,
            'balance' => 0,
            'pending_balance' => 0,
        ]);

        $buyerUser = User::factory()->create();
        $buyer = Buyer::factory()->create(['user_id' => $buyerUser->id]);

        $this->transaction = Transaction::create([
            'code' => 'LEDGER_001',
            'buyer_id' => $buyer->id,
            'store_id' => $store->id,
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
    }

    private function write(string $type, ?string $uniqueRef): StoreBalanceHistory
    {
        return $this->storeBalance->storeBalanceHistories()->create([
            'type' => $type,
            'reference_id' => $this->transaction->id,
            'reference_type' => Transaction::class,
            'unique_ref' => $uniqueRef,
            'amount' => 1000,
            'remarks' => 'uji',
        ]);
    }

    public function test_the_database_refuses_a_second_credit_for_the_same_transaction(): void
    {
        $this->write('pending_income', 'pending_income:'.$this->transaction->id);

        $this->expectException(QueryException::class);
        $this->write('pending_income', 'pending_income:'.$this->transaction->id);
    }

    public function test_release_and_refund_are_each_limited_to_one_entry(): void
    {
        $this->write('released', 'released:'.$this->transaction->id);
        $this->write('refunded', 'refunded:'.$this->transaction->id);

        // Ketiganya hidup berdampingan: yang dilarang hanya pengulangan
        // mutasi yang sama untuk transaksi yang sama.
        $this->assertSame(2, StoreBalanceHistory::whereNotNull('unique_ref')->count());

        $this->expectException(QueryException::class);
        $this->write('released', 'released:'.$this->transaction->id);
    }

    public function test_entries_without_a_unique_ref_may_repeat(): void
    {
        // Baris lama dan tipe yang wajar berulang tidak boleh terhalang:
        // MySQL mengecualikan NULL dari unique index.
        $this->write('income', null);
        $this->write('income', null);
        $this->write('income', null);

        $this->assertSame(3, StoreBalanceHistory::whereNull('unique_ref')->count());
    }
}
