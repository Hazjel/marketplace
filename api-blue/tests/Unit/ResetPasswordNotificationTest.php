<?php

namespace Tests\Unit;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use PHPUnit\Framework\TestCase;

/**
 * Regresi untuk bug: forgot-password lama mengirim SMTP synchronous di
 * request HTTP (butuh ~111 detik saat Resend lambat, menahan worker
 * PHP-FPM). Notifikasi ini wajib ShouldQueue supaya pengiriman jalan di
 * container `queue`, bukan memblokir response.
 */
class ResetPasswordNotificationTest extends TestCase
{
    public function test_notification_is_queued(): void
    {
        $this->assertInstanceOf(
            ShouldQueue::class,
            new ResetPasswordNotification('dummy-token'),
        );
    }
}
