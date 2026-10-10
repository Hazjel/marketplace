<?php

namespace Tests\Feature;

use App\Events\TransactionStatusUpdated;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\RefundStatusNotification;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The buyer is told where their money went: push body for every refund
 * state, and one email per final state (refunded, manual_required).
 */
class RefundNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $buyerUser;

    /** @var list<string> push bodies sent to the buyer */
    private array $pushes = [];

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->mock(PushNotificationService::class, function ($mock) {
            $mock->shouldReceive('sendToUser')->andReturnUsing(function ($user, $title, $body) {
                $this->pushes[] = $body;
            });
        });

        $this->buyerUser = User::factory()->create();
        $this->buyerUser->buyer()->create(['phone_number' => '0812', 'city' => 'B', 'address' => 'Jl']);
    }

    private function order(array $attributes): Transaction
    {
        $seller = User::factory()->create();
        $store = Store::create([
            'user_id' => $seller->id, 'name' => 'S', 'username' => 's-refund-'.$seller->id,
            'logo' => 'd.png', 'about' => 'a', 'phone' => '0812', 'address_id' => '1',
            'city' => 'Jakarta', 'address' => 'Jl', 'postal_code' => '12345', 'is_verified' => true,
        ]);

        return Transaction::create($attributes + [
            'code' => 'BLUE_REFUND_1', 'buyer_id' => $this->buyerUser->buyer->id, 'store_id' => $store->id,
            'address_id' => 1, 'address' => 'Jl', 'city' => 'Jakarta', 'postal_code' => '12345',
            'shipping' => 'JNE', 'shipping_type' => 'REG', 'shipping_cost' => 0, 'tax' => 0,
            'grand_total' => 25000, 'balance_used' => 0, 'payment_status' => 'failed', 'delivery_status' => 'cancelled',
        ]);
    }

    private function fire(Transaction $transaction): string
    {
        event(new TransactionStatusUpdated($transaction->fresh()));

        return end($this->pushes);
    }

    public function test_processing_push_mentions_both_parts_and_sends_no_email()
    {
        $order = $this->order(['refund_status' => 'processing', 'refund_amount' => 18100, 'balance_used' => 6900]);

        $this->assertSame(
            'Pesananmu dibatalkan. Pengembalian Rp18.100 ke metode pembayaranmu sedang diproses. Rp6.900 sudah kembali ke Saldo Blukios.',
            $this->fire($order),
        );
        Notification::assertNothingSent();
    }

    public function test_refunded_through_midtrans_is_emailed_once_even_if_the_event_fires_twice()
    {
        $order = $this->order(['refund_status' => 'refunded', 'refund_method' => 'midtrans', 'refund_amount' => 25000]);

        $text = 'Rp25.000 sudah dikembalikan ke metode pembayaranmu. Waktu dana masuk mengikuti bank atau penyedia e-wallet.';
        $this->assertSame($text, $this->fire($order));
        $this->fire($order);

        Notification::assertSentToTimes($this->buyerUser, RefundStatusNotification::class, 1);
        Notification::assertSentTo($this->buyerUser, RefundStatusNotification::class, function ($n) use ($order, $text) {
            $mail = $n->toMail($this->buyerUser);

            return $n->message === $text
                && $mail->subject === 'Pengembalian dana pesanan BLUE_REFUND_1'
                && str_ends_with($mail->actionUrl, '/'.$this->buyerUser->username.'/transaction/'.$order->id);
        });
    }

    public function test_refunded_to_balance_adds_both_parts()
    {
        $order = $this->order(['refund_status' => 'refunded', 'refund_method' => 'balance', 'refund_amount' => 18100, 'balance_used' => 6900]);

        $this->assertSame('Rp25.000 sudah masuk ke Saldo Blukios dan bisa dipakai untuk belanja.', $this->fire($order));
    }

    public function test_fully_balance_paid_order_counts_only_the_balance()
    {
        $order = $this->order(['refund_status' => 'refunded', 'refund_method' => 'balance', 'refund_amount' => 0, 'balance_used' => 25000]);

        $this->assertSame('Rp25.000 sudah masuk ke Saldo Blukios dan bisa dipakai untuk belanja.', $this->fire($order));
        Notification::assertSentToTimes($this->buyerUser, RefundStatusNotification::class, 1);
    }

    public function test_refunded_manually_names_the_buyers_account()
    {
        $order = $this->order(['refund_status' => 'refunded', 'refund_method' => 'manual', 'refund_amount' => 18100, 'balance_used' => 6900]);

        $this->assertSame('Rp18.100 sudah ditransfer ke rekening yang kamu isi. Rp6.900 sudah kembali ke Saldo Blukios.', $this->fire($order));
    }

    public function test_manual_required_is_pushed_and_emailed()
    {
        $order = $this->order(['refund_status' => 'manual_required', 'refund_method' => 'manual', 'refund_amount' => 25000]);

        $this->assertSame('Pengembalian dana Rp25.000 sedang kami periksa dan akan segera diselesaikan.', $this->fire($order));
        Notification::assertSentToTimes($this->buyerUser, RefundStatusNotification::class, 1);
    }

    public function test_manual_required_then_refunded_sends_one_email_each()
    {
        $order = $this->order(['refund_status' => 'manual_required', 'refund_method' => 'manual', 'refund_amount' => 25000]);
        $this->fire($order);
        $order->update(['refund_status' => 'refunded']);
        $this->fire($order);

        Notification::assertSentToTimes($this->buyerUser, RefundStatusNotification::class, 2);
    }

    public function test_order_without_refund_keeps_the_delivery_status_text()
    {
        $order = $this->order(['payment_status' => 'paid', 'delivery_status' => 'delivering']);

        $this->assertSame('Pesananmu sedang dikirim.', $this->fire($order));
        Notification::assertNothingSent();
    }
}
