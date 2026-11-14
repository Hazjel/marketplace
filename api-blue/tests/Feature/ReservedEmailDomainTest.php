<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\RefundStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Users on reserved domains (example.com, *.test, ...) have no inbox:
 * Resend answered "550 Invalid to field" and every queued notification to
 * them became a permanently failed job. No mail may leave for them.
 */
class ReservedEmailDomainTest extends TestCase
{
    use RefreshDatabase;

    private function sentMessages(): int
    {
        /** @var ArrayTransport $transport */
        $transport = Mail::getSymfonyTransport();

        return $transport->messages()->count();
    }

    public function test_refund_notification_is_not_mailed_to_reserved_domain(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com']);

        $user->notify(new RefundStatusNotification('tx-1', 'BLK1', 'Dana dikembalikan.'));

        $this->assertSame(0, $this->sentMessages());
    }

    public function test_refund_notification_is_mailed_to_real_domain(): void
    {
        $user = User::factory()->create(['email' => 'buyer@gmail.com']);

        $user->notify(new RefundStatusNotification('tx-1', 'BLK1', 'Dana dikembalikan.'));

        $this->assertSame(1, $this->sentMessages());
    }

    /** @return array<string, array{string}> */
    public static function reservedEmails(): array
    {
        return [
            'example.com' => ['a@example.com'],
            'uppercase example.org' => ['a@EXAMPLE.ORG'],
            'subdomain of example.net' => ['a@shop.example.net'],
            '.test' => ['a@x.test'],
            '.invalid' => ['a@y.invalid'],
            '.localhost' => ['a@z.localhost'],
            '.example' => ['a@foo.example'],
        ];
    }

    #[DataProvider('reservedEmails')]
    public function test_reserved_domain_routes_nowhere(string $email): void
    {
        $this->assertNull((new User(['email' => $email]))->routeNotificationForMail());
    }

    /** @return array<string, array{string}> */
    public static function realEmails(): array
    {
        return [
            'gmail' => ['a@gmail.com'],
            'blukios.store' => ['a@blukios.store'],
            'example.co' => ['a@example.co'],
            'notexample.com' => ['a@notexample.com'],
            'blukios-test.com' => ['a@blukios-test.com'],
        ];
    }

    #[DataProvider('realEmails')]
    public function test_real_domain_routes_to_email(string $email): void
    {
        $this->assertSame($email, (new User(['email' => $email]))->routeNotificationForMail());
    }
}
