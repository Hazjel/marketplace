<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\Store;
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
 * Mirrors PushNotificationDeliveryTest's shape: proof that a real chat
 * request reaches PushNotificationService, not just that the event fires.
 */
class PushNotificationChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_sending_a_message_pushes_to_the_receivers_registered_device()
    {
        $sender = User::factory()->create(['name' => 'Budi']);
        $sender->assignRole('buyer');

        $receiver = User::factory()->create();
        $receiver->assignRole('store');
        Store::factory()->create(['user_id' => $receiver->id]);
        DeviceToken::create(['user_id' => $receiver->id, 'token' => 'receiver-device-1', 'platform' => 'android']);

        $report = MulticastSendReport::withItems([
            SendReport::success(MessageTarget::with(MessageTarget::TOKEN, 'receiver-device-1'), []),
        ]);

        $this->mock(Messaging::class, function ($mock) use ($report) {
            $mock->shouldReceive('sendMulticast')
                ->once()
                ->withArgs(fn ($message, $tokens) => $tokens === ['receiver-device-1'])
                ->andReturn($report);
        });

        $response = $this->actingAs($sender, 'sanctum')->postJson('/api/chat/send', [
            'receiver_id' => $receiver->id,
            'message' => 'Halo, ada stok?',
        ]);

        $response->assertStatus(201);
    }

    public function test_sender_without_registered_device_does_not_crash_send()
    {
        $sender = User::factory()->create();
        $sender->assignRole('buyer');

        $receiver = User::factory()->create();
        $receiver->assignRole('store');
        Store::factory()->create(['user_id' => $receiver->id]);
        // No device token registered for $receiver — sendToUser must no-op
        // cleanly rather than touching Firebase at all.

        $response = $this->actingAs($sender, 'sanctum')->postJson('/api/chat/send', [
            'receiver_id' => $receiver->id,
            'message' => 'Halo',
        ]);

        $response->assertStatus(201);
    }
}
