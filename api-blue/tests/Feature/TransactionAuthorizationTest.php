<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Matriks otorisasi transaksi.
 *
 * showByCode(), update(), dan destroy() dulu mengambil transaksi hanya dari
 * id/kode lalu langsung mengerjakannya. Punya permission transaction-edit
 * berarti bisa mengubah transaksi toko mana pun, bukan cuma milik sendiri.
 */
class TransactionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $buyerUser;

    private User $sellerUser;

    private User $otherSeller;

    private User $otherBuyer;

    private User $admin;

    private Transaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        [$this->buyerUser, $buyer] = $this->makeBuyer();
        [$this->otherBuyer] = $this->makeBuyer();
        [$this->sellerUser, $store] = $this->makeSeller();
        [$this->otherSeller] = $this->makeSeller();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        // Dibangun eksplisit, bukan lewat factory: afterCreating milik
        // TransactionFactory mengarang 1-5 produk bernama acak (slug-nya bisa
        // bentrok) dan mengacak payment_status. Matriks otorisasi tidak
        // memerlukan detail transaksi, dan butuh fixture yang deterministik.
        $this->transaction = Transaction::create([
            'code' => 'BLUE_AUTHZ_001',
            'buyer_id' => $buyer->id,
            'store_id' => $store->id,
            'address_id' => 1,
            'address' => 'Jl. Buyer',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'shipping' => 'JNE',
            'shipping_type' => 'REG',
            'shipping_cost' => 10000,
            'tax' => 11000,
            'grand_total' => 121000,
            'payment_status' => 'paid',
            'delivery_status' => 'delivering',
            'admin_fee' => 1000,
        ]);
    }

    /** @return array{0: User, 1: Buyer} */
    private function makeBuyer(): array
    {
        $user = User::factory()->create();
        $user->assignRole('buyer');
        $buyer = Buyer::factory()->create(['user_id' => $user->id]);

        return [$user, $buyer];
    }

    /** @return array{0: User, 1: Store} */
    private function makeSeller(): array
    {
        $user = User::factory()->create();
        $user->assignRole('store');
        $store = Store::factory()->create(['user_id' => $user->id]);

        return [$user, $store];
    }

    private function actor(string $who): User
    {
        return match ($who) {
            'owning buyer' => $this->buyerUser,
            'other buyer' => $this->otherBuyer,
            'owning seller' => $this->sellerUser,
            'other seller' => $this->otherSeller,
            'admin' => $this->admin,
        };
    }

    public static function viewMatrix(): array
    {
        return [
            'owning buyer may view' => ['owning buyer', 200],
            'owning seller may view' => ['owning seller', 200],
            'admin may view' => ['admin', 200],
            'unrelated buyer may not view' => ['other buyer', 403],
            'unrelated seller may not view' => ['other seller', 403],
        ];
    }

    #[DataProvider('viewMatrix')]
    public function test_show_enforces_ownership(string $who, int $expected): void
    {
        $this->actingAs($this->actor($who), 'sanctum')
            ->getJson("/api/transaction/{$this->transaction->id}")
            ->assertStatus($expected);
    }

    #[DataProvider('viewMatrix')]
    public function test_show_by_code_enforces_ownership(string $who, int $expected): void
    {
        $this->actingAs($this->actor($who), 'sanctum')
            ->getJson("/api/transaction/code/{$this->transaction->code}")
            ->assertStatus($expected);
    }

    public static function updateMatrix(): array
    {
        return [
            'owning seller may update shipping' => ['owning seller', 200],
            'admin may update shipping' => ['admin', 200],
            'unrelated seller may not update' => ['other seller', 403],
            'buyer may not update shipping' => ['owning buyer', 403],
        ];
    }

    #[DataProvider('updateMatrix')]
    public function test_update_enforces_ownership(string $who, int $expected): void
    {
        $this->actingAs($this->actor($who), 'sanctum')
            ->putJson("/api/transaction/{$this->transaction->id}", [
                'delivery_status' => 'delivering',
                'tracking_number' => 'JNE-123',
            ])
            ->assertStatus($expected);
    }

    public static function deleteMatrix(): array
    {
        return [
            'owning seller may delete' => ['owning seller', 200],
            'admin may delete' => ['admin', 200],
            'unrelated seller may not delete' => ['other seller', 403],
            'buyer may not delete' => ['owning buyer', 403],
        ];
    }

    #[DataProvider('deleteMatrix')]
    public function test_destroy_enforces_ownership(string $who, int $expected): void
    {
        $this->actingAs($this->actor($who), 'sanctum')
            ->deleteJson("/api/transaction/{$this->transaction->id}")
            ->assertStatus($expected);
    }

    public function test_unrelated_seller_cannot_delete_someone_elses_transaction(): void
    {
        $this->actingAs($this->otherSeller, 'sanctum')
            ->deleteJson("/api/transaction/{$this->transaction->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('transactions', ['id' => $this->transaction->id]);
    }

    public function test_unrelated_seller_cannot_change_shipping_of_someone_elses_transaction(): void
    {
        $this->actingAs($this->otherSeller, 'sanctum')
            ->putJson("/api/transaction/{$this->transaction->id}", [
                'delivery_status' => 'completed',
                'tracking_number' => 'HIJACKED',
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('transactions', [
            'id' => $this->transaction->id,
            'tracking_number' => 'HIJACKED',
        ]);
    }

    public function test_only_the_owning_buyer_may_complete_an_order(): void
    {
        // complete() melepas dana escrow, jadi penjual dan admin sekalipun
        // tidak boleh memicunya atas nama pembeli.
        foreach (['other buyer', 'owning seller', 'admin'] as $who) {
            $this->actingAs($this->actor($who), 'sanctum')
                ->postJson("/api/transaction/{$this->transaction->id}/complete")
                ->assertStatus(403);
        }
    }

    public function test_payment_status_check_is_limited_to_the_parties_involved(): void
    {
        $this->actingAs($this->otherSeller, 'sanctum')
            ->postJson("/api/transaction/{$this->transaction->id}/check-status")
            ->assertStatus(403);

        $this->actingAs($this->otherBuyer, 'sanctum')
            ->postJson("/api/transaction/{$this->transaction->id}/check-status")
            ->assertStatus(403);
    }
}
