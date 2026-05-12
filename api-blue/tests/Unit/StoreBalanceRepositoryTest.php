<?php

namespace Tests\Unit;

use App\Models\Store;
use App\Models\StoreBalance;
use App\Models\User;
use App\Repositories\StoreBalanceRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreBalanceRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private StoreBalanceRepository $repository;
    private StoreBalance $storeBalance;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->repository = new StoreBalanceRepository;

        // Create a store with balance
        $user = User::factory()->create();
        $user->assignRole('store');

        $store = Store::create([
            'user_id' => $user->id,
            'name' => 'Test Store',
            'username' => 'teststore',
            'logo' => 'default.png',
            'about' => 'Test',
            'phone' => '08123456789',
            'address_id' => '1',
            'city' => 'Jakarta',
            'address' => 'Jl. Test',
            'postal_code' => '12345',
            'is_verified' => true,
        ]);

        $this->storeBalance = StoreBalance::create([
            'store_id' => $store->id,
            'balance' => 500000,
            'pending_balance' => 0,
        ]);
    }

    // ==========================================
    // creditPending Tests
    // ==========================================

    public function test_credit_pending_increases_pending_balance()
    {
        $result = $this->repository->creditPending($this->storeBalance->id, 100000);

        $this->assertEquals(100000, (float) $result->pending_balance);
        $this->assertEquals(500000, (float) $result->balance); // available balance unchanged
    }

    public function test_credit_pending_accumulates_multiple_credits()
    {
        $this->repository->creditPending($this->storeBalance->id, 100000);
        $result = $this->repository->creditPending($this->storeBalance->id, 50000);

        $this->assertEquals(150000, (float) $result->pending_balance);
        $this->assertEquals(500000, (float) $result->balance);
    }

    public function test_credit_pending_throws_exception_for_invalid_id()
    {
        $this->expectException(\Exception::class);

        $this->repository->creditPending('non-existent-id', 100000);
    }

    // ==========================================
    // releasePending Tests
    // ==========================================

    public function test_release_pending_moves_funds_to_available_balance()
    {
        // Setup: add pending balance first
        $this->storeBalance->update(['pending_balance' => 200000]);

        $result = $this->repository->releasePending($this->storeBalance->id, 200000);

        $this->assertEquals(0, (float) $result->pending_balance);
        $this->assertEquals(700000, (float) $result->balance); // 500000 + 200000
    }

    public function test_release_pending_partial_amount()
    {
        // Setup: add pending balance
        $this->storeBalance->update(['pending_balance' => 300000]);

        $result = $this->repository->releasePending($this->storeBalance->id, 100000);

        $this->assertEquals(200000, (float) $result->pending_balance);
        $this->assertEquals(600000, (float) $result->balance); // 500000 + 100000
    }

    public function test_release_pending_throws_exception_if_insufficient_pending()
    {
        $this->storeBalance->update(['pending_balance' => 50000]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Pending balance tidak mencukupi untuk dirilis');

        $this->repository->releasePending($this->storeBalance->id, 100000);
    }

    public function test_release_pending_does_not_affect_database_on_failure()
    {
        $this->storeBalance->update(['pending_balance' => 50000]);

        try {
            $this->repository->releasePending($this->storeBalance->id, 100000);
        } catch (\Exception $e) {
            // Expected
        }

        $this->storeBalance->refresh();
        $this->assertEquals(50000, (float) $this->storeBalance->pending_balance);
        $this->assertEquals(500000, (float) $this->storeBalance->balance);
    }

    // ==========================================
    // refundPending Tests
    // ==========================================

    public function test_refund_pending_reduces_pending_balance()
    {
        $this->storeBalance->update(['pending_balance' => 200000]);

        $result = $this->repository->refundPending($this->storeBalance->id, 200000);

        $this->assertEquals(0, (float) $result->pending_balance);
        $this->assertEquals(500000, (float) $result->balance); // available balance unchanged
    }

    public function test_refund_pending_partial_amount()
    {
        $this->storeBalance->update(['pending_balance' => 200000]);

        $result = $this->repository->refundPending($this->storeBalance->id, 80000);

        $this->assertEquals(120000, (float) $result->pending_balance);
        $this->assertEquals(500000, (float) $result->balance);
    }

    public function test_refund_pending_sets_to_zero_if_amount_exceeds_pending()
    {
        $this->storeBalance->update(['pending_balance' => 50000]);

        $result = $this->repository->refundPending($this->storeBalance->id, 100000);

        // Should set to 0, not negative
        $this->assertEquals(0, (float) $result->pending_balance);
        $this->assertEquals(500000, (float) $result->balance);
    }

    // ==========================================
    // Existing methods still work correctly
    // ==========================================

    public function test_credit_only_affects_available_balance()
    {
        $this->storeBalance->update(['pending_balance' => 100000]);

        $result = $this->repository->credit($this->storeBalance->id, 50000);

        $this->assertEquals(550000, (float) $result->balance);
        $this->assertEquals(100000, (float) $result->pending_balance); // unchanged
    }

    public function test_debit_only_affects_available_balance()
    {
        $this->storeBalance->update(['pending_balance' => 100000]);

        $result = $this->repository->debit($this->storeBalance->id, 50000);

        $this->assertEquals(450000, (float) $result->balance);
        $this->assertEquals(100000, (float) $result->pending_balance); // unchanged
    }

    public function test_debit_throws_exception_if_insufficient_available_balance()
    {
        // balance = 500000, but trying to debit 600000
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Saldo Tidak Mencukupi');

        $this->repository->debit($this->storeBalance->id, 600000);
    }

    public function test_debit_cannot_use_pending_balance()
    {
        // balance = 100, pending = 500000
        $this->storeBalance->update(['balance' => 100, 'pending_balance' => 500000]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Saldo Tidak Mencukupi');

        // Even though total (balance + pending) > 200, debit should only check balance
        $this->repository->debit($this->storeBalance->id, 200);
    }
}
