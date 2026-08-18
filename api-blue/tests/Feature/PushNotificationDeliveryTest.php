<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * End-to-end proof that a real status-changing request actually reaches
 * PushNotificationService, not just that the event fires (already covered
 * by unrelated broadcast tests) — this is what confirms the auto-discovered
 * listener (SendPushOnTransactionStatusUpdated) is really wired up.
 */
class PushNotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_updating_delivery_status_pushes_to_the_buyers_registered_device()
    {
        $seller = User::factory()->create();
        $seller->assignRole('store');
        $store = Store::create([
            'user_id' => $seller->id, 'name' => 'S', 'username' => 's-push',
            'logo' => 'd.png', 'about' => 'a', 'phone' => '08123456789', 'address_id' => '1',
            'city' => 'Jakarta', 'address' => 'Jl', 'postal_code' => '12345', 'is_verified' => true,
        ]);
        $store->storeBalance()->create(['balance' => 0]);

        $buyerUser = User::factory()->create();
        $buyerUser->assignRole('buyer');
        $buyer = $buyerUser->buyer()->create(['phone_number' => '0812', 'city' => 'B', 'address' => 'Jl']);
        DeviceToken::create(['user_id' => $buyerUser->id, 'token' => 'buyer-device-1', 'platform' => 'android']);

        $category = ProductCategory::create(['name' => 'G', 'slug' => 'g-push', 'description' => 'd']);
        $product = Product::create([
            'store_id' => $store->id, 'product_category_id' => $category->id, 'name' => 'P',
            'slug' => 'p-push', 'description' => 'd', 'price' => 100000, 'stock' => 10, 'weight' => 1, 'condition' => 'new',
        ]);

        $transaction = Transaction::create([
            'code' => 'BLUE_PUSH_001', 'buyer_id' => $buyer->id, 'store_id' => $store->id,
            'address_id' => 1, 'address' => 'Jl. Buyer', 'city' => 'Jakarta', 'postal_code' => '12345',
            'shipping' => 'JNE', 'shipping_type' => 'REG', 'shipping_cost' => 15000,
            'tax' => 11000, 'grand_total' => 126000, 'payment_status' => 'paid', 'delivery_status' => 'pending',
        ]);
        TransactionDetail::create(['transaction_id' => $transaction->id, 'product_id' => $product->id, 'qty' => 1, 'subtotal' => 100000]);

        $report = MulticastSendReport::withItems([
            SendReport::success(MessageTarget::with(MessageTarget::TOKEN, 'buyer-device-1'), []),
        ]);

        $this->mock(Messaging::class, function ($mock) use ($report) {
            $mock->shouldReceive('sendMulticast')
                ->once()
                ->withArgs(fn ($message, $tokens) => $tokens === ['buyer-device-1'])
                ->andReturn($report);
        });

        $response = $this->actingAs($seller, 'sanctum')->putJson("/api/transaction/{$transaction->id}", [
            'delivery_status' => 'processing',
        ]);

        $response->assertStatus(200);
    }
}
