<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\BuyerBalanceHistory;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\BuyerBalanceRepository;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Saldo Blukios is visible only to its owner, through GET /api/balance.
 */
class BuyerBalanceTest extends TestCase
{
    use RefreshDatabase;

    // Distinctive enough to grep a whole response body for.
    private const BALANCE = 777000;

    private User $buyerUser;

    private Buyer $buyer;

    private Transaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        [$this->buyerUser, $this->buyer] = $this->makeBuyer();

        $seller = User::factory()->create();
        $seller->assignRole('store');
        $store = Store::factory()->create(['user_id' => $seller->id]);

        $this->transaction = Transaction::create([
            'code' => 'BLUE_SALDO_001',
            'buyer_id' => $this->buyer->id,
            'store_id' => $store->id,
            'address_id' => 1,
            'address' => 'Jl. Buyer',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'shipping' => 'JNE',
            'shipping_type' => 'REG',
            'shipping_cost' => 10000,
            'tax' => 0,
            'grand_total' => 121000,
            'payment_status' => 'paid',
            'delivery_status' => 'delivering',
        ]);

        $repository = new BuyerBalanceRepository;
        $repository->credit($this->buyer->id, '800000', BuyerBalanceHistory::TYPE_REFUND, 'refund:saldo-1', $this->transaction, 'Refund pesanan');
        $repository->debit($this->buyer->id, '23000', BuyerBalanceHistory::TYPE_PAYMENT, 'payment:saldo-2', null, 'Belanja');
    }

    /** @return array{0: User, 1: Buyer} */
    private function makeBuyer(): array
    {
        $user = User::factory()->create();
        $user->assignRole('buyer');

        return [$user, Buyer::create(['user_id' => $user->id, 'phone_number' => '0813'])];
    }

    public function test_the_owner_sees_the_balance_and_history_as_integers(): void
    {
        $response = $this->actingAs($this->buyerUser, 'sanctum')
            ->getJson('/api/balance')
            ->assertOk()
            ->assertJsonPath('data.balance', self::BALANCE)
            ->assertJsonPath('data.histories.meta.total', 2);

        $rows = $response->json('data.histories.data');
        // Newest first.
        $this->assertSame(['payment', 'refund'], array_column($rows, 'type'));
        $this->assertSame([-23000, 800000], array_column($rows, 'amount'));
        $this->assertSame([null, 'BLUE_SALDO_001'], array_column($rows, 'reference_code'));
        $this->assertSame('Refund pesanan', $rows[1]['remarks']);
        $this->assertArrayHasKey('created_at', $rows[0]);
        $this->assertSame(BuyerBalanceHistory::where('unique_ref', 'payment:saldo-2')->value('id'), $rows[0]['id']);
    }

    public function test_another_buyer_sees_only_their_own_empty_balance(): void
    {
        [$otherUser] = $this->makeBuyer();

        $response = $this->actingAs($otherUser, 'sanctum')
            ->getJson('/api/balance')
            ->assertOk()
            ->assertJsonPath('data.balance', 0)
            ->assertJsonPath('data.histories.data', []);

        $this->assertStringNotContainsString((string) self::BALANCE, $response->getContent());
    }

    public function test_a_user_without_a_buyer_profile_is_forbidden(): void
    {
        $seller = User::factory()->create();
        $seller->assignRole('store');

        $this->actingAs($seller, 'sanctum')->getJson('/api/balance')->assertForbidden();
    }

    public function test_a_guest_is_unauthenticated(): void
    {
        $this->getJson('/api/balance')->assertUnauthorized();
    }

    public function test_per_page_is_bounded(): void
    {
        $this->actingAs($this->buyerUser, 'sanctum')
            ->getJson('/api/balance?per_page=500')
            ->assertUnprocessable();

        $this->actingAs($this->buyerUser, 'sanctum')
            ->getJson('/api/balance?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.histories.data')
            ->assertJsonPath('data.histories.meta.last_page', 2);
    }

    public function test_me_does_not_expose_the_balance(): void
    {
        $response = $this->actingAs($this->buyerUser, 'sanctum')
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.buyer.id', $this->buyer->id)
            ->assertJsonMissingPath('data.buyer.balance');

        $this->assertStringNotContainsString((string) self::BALANCE, $response->getContent());
    }

    public function test_transaction_resources_do_not_expose_the_balance(): void
    {
        $response = $this->actingAs($this->buyerUser, 'sanctum')
            ->getJson("/api/transaction/{$this->transaction->id}")
            ->assertOk()
            ->assertJsonPath('data.buyer.id', $this->buyer->id);

        $this->assertStringNotContainsString((string) self::BALANCE, $response->getContent());
    }
}
