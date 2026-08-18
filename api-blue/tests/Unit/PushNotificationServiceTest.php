<?php

namespace Tests\Unit;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use Mockery;
use Tests\TestCase;

class PushNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_does_nothing_when_user_has_no_registered_devices()
    {
        $user = User::factory()->create();

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldNotReceive('sendMulticast');

        (new PushNotificationService($messaging))->sendToUser($user, 'Title', 'Body');

        // No assertion needed beyond the mock expectation above — if
        // sendMulticast were called, Mockery would fail the test on tearDown.
        $this->assertTrue(true);
    }

    public function test_sends_to_every_registered_device_and_prunes_invalid_tokens()
    {
        $user = User::factory()->create();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'token-valid', 'platform' => 'android']);
        DeviceToken::create(['user_id' => $user->id, 'token' => 'token-dead', 'platform' => 'android']);

        // MulticastSendReport and SendReport are final — built for real
        // (not mocked) so invalidTokens() runs its actual filtering logic
        // against a genuinely invalid-token-shaped error, rather than us
        // just asserting whatever we told a mock to say.
        $report = MulticastSendReport::withItems([
            SendReport::success(MessageTarget::with(MessageTarget::TOKEN, 'token-valid'), []),
            SendReport::failure(
                MessageTarget::with(MessageTarget::TOKEN, 'token-dead'),
                new InvalidArgument('Requested entity was not found: invalid registration token'),
            ),
        ]);

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('sendMulticast')
            ->once()
            ->withArgs(function ($message, $tokens) {
                sort($tokens);

                return $tokens === ['token-dead', 'token-valid'];
            })
            ->andReturn($report);

        (new PushNotificationService($messaging))->sendToUser($user, 'Pesanan ABC', 'Sedang dikirim');

        $this->assertDatabaseMissing('device_tokens', ['token' => 'token-dead']);
        $this->assertDatabaseHas('device_tokens', ['token' => 'token-valid']);
    }

    public function test_a_send_failure_does_not_throw()
    {
        $user = User::factory()->create();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'token-1']);

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('sendMulticast')->once()->andThrow(new \RuntimeException('Firebase unreachable'));

        // Should not bubble up — a push failure is a side effect, not
        // something that should turn the caller's own request into a 500.
        (new PushNotificationService($messaging))->sendToUser($user, 'Title', 'Body');
        $this->assertTrue(true);
    }
}
