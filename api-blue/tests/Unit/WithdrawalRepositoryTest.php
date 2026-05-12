<?php

namespace Tests\Unit;

use App\Models\Store;
use App\Models\StoreBalance;
use App\Models\User;
use App\Repositories\WithdrawalRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawalRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private WithdrawalRepository $repository;
    private StoreBalance $storeBalance;
    private User $storeUser;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->repository = new WithdrawalRepository;

        // Create store user
        $this->storeUser = User::factory()->create();
        $this->storeUser->assignRole('store');

        $store = Store::create([
            'user_id' => $this->storeUser->id,
            'name' => 'Withdrawal Test Store',
            'username' => 'withdrawalstore',
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
            'pending_balance' => 200000,
        ]);
    }

    public function test_withdrawal_succeeds_when_amount_within_available_balance()
    {
        $data = [
            'store_balance_id' => $this->storeBalance->id,
            'amount' => 300000,
            'bank_account_name' => 'John Doe',
            'bank_account_number' => '1234567890',
            'bank_name' => 'BCA',
        ];

        $withdrawal = $this->repository->create($data);

        $this->assertNotNull($withdrawal);
        $this->assertEquals(300000, (float) $withdrawal->amount);
        $this->assertEquals('pending', $withdrawal->status);

        // Check balance was deducted
        $this->storeBalance->refresh();
        $this->assertEquals(200000, (float) $this->storeBalance->balance); // 500000 - 300000
        $this->assertEquals(200000, (float) $this->storeBalance->pending_balance); // unchanged
    }

    public function test_withdrawal_fails_when_amount_exceeds_available_balance()
    {
        // balance = 500000, but pending_balance = 200000
        // Trying to withdraw 600000 which exceeds available balance
        $data = [
            'store_balance_id' => $this->storeBalance->id,
            'amount' => 600000,
            'bank_account_name' => 'John Doe',
            'bank_account_number' => '1234567890',
            'bank_name' => 'BCA',
        ];

        $this->expectException(\Exception::class);

        $this->repository->create($data);
    }

    public function test_withdrawal_fails_message_mentions_escrow_when_pending_exists()
    {
        // balance = 500000, pending = 200000
        // Try to withdraw 600000 (more than available)
        $data = [
            'store_balance_id' => $this->storeBalance->id,
            'amount' => 600000,
            'bank_account_name' => 'John Doe',
            'bank_account_number' => '1234567890',
            'bank_name' => 'BCA',
        ];

        try {
            $this->repository->create($data);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Saldo tersedia tidak mencukupi', $e->getMessage());
            $this->assertStringContainsString('ditahan', $e->getMessage());
        }
    }

    public function test_withdrawal_cannot_use_pending_balance()
    {
        // Set balance to only 100, but pending_balance is 200000
        // Total "funds" = 200100, but only 100 is available
        $this->storeBalance->update(['balance' => 100, 'pending_balance' => 200000]);

        $data = [
            'store_balance_id' => $this->storeBalance->id,
            'amount' => 150, // More than available (100) but less than total (200100)
            'bank_account_name' => 'John Doe',
            'bank_account_number' => '1234567890',
            'bank_name' => 'BCA',
        ];

        $this->expectException(\Exception::class);

        $this->repository->create($data);
    }

    public function test_withdrawal_exact_available_balance_succeeds()
    {
        $data = [
            'store_balance_id' => $this->storeBalance->id,
            'amount' => 500000, // Exactly the available balance
            'bank_account_name' => 'John Doe',
            'bank_account_number' => '1234567890',
            'bank_name' => 'BCA',
        ];

        $withdrawal = $this->repository->create($data);

        $this->assertNotNull($withdrawal);
        $this->storeBalance->refresh();
        $this->assertEquals(0, (float) $this->storeBalance->balance);
        $this->assertEquals(200000, (float) $this->storeBalance->pending_balance); // still held
    }

    public function test_withdrawal_creates_history_record()
    {
        $data = [
            'store_balance_id' => $this->storeBalance->id,
            'amount' => 100000,
            'bank_account_name' => 'John Doe',
            'bank_account_number' => '1234567890',
            'bank_name' => 'BCA',
        ];

        $withdrawal = $this->repository->create($data);

        $this->assertDatabaseHas('store_balance_histories', [
            'store_balance_id' => $this->storeBalance->id,
            'type' => 'withdrawal',
            'reference_id' => $withdrawal->id,
        ]);
    }

    public function test_withdrawal_does_not_affect_database_on_failure()
    {
        $data = [
            'store_balance_id' => $this->storeBalance->id,
            'amount' => 999999, // Way more than available
            'bank_account_name' => 'John Doe',
            'bank_account_number' => '1234567890',
            'bank_name' => 'BCA',
        ];

        try {
            $this->repository->create($data);
        } catch (\Exception $e) {
            // Expected
        }

        // Balance should be unchanged (transaction rolled back)
        $this->storeBalance->refresh();
        $this->assertEquals(500000, (float) $this->storeBalance->balance);
        $this->assertEquals(200000, (float) $this->storeBalance->pending_balance);

        // No withdrawal record should exist
        $this->assertDatabaseCount('withdrawals', 0);
    }
}
