<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the buyer what happened to their money. Takes the text, not the
 * Transaction, so a queued mail says what was true when it was sent off,
 * not whatever the row says by the time the worker gets to it.
 */
class RefundStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $transactionId,
        public readonly string $code,
        public readonly string $message,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pengembalian dana pesanan '.$this->code)
            ->greeting('Halo, '.$notifiable->name.'!')
            ->line('Kabar pengembalian dana untuk pesanan **'.$this->code.'**:')
            ->line($this->message)
            ->action('Lihat Pesanan', config('marketplace.storefront_url').'/'.rawurlencode((string) $notifiable->username).'/transaction/'.$this->transactionId)
            ->salutation('Salam hangat, Tim Blukios');
    }
}
