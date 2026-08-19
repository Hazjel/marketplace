<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Services\PushNotificationService;
use Illuminate\Support\Str;

/**
 * Auto-discovered by Laravel (handle() type-hints the event), mirrors
 * SendPushOnTransactionStatusUpdated. Chat previously only reached the
 * receiver via the Reverb websocket — meaning a message sent while the
 * receiver's app was backgrounded or closed never surfaced at all. This
 * closes that gap the same way transaction status updates already work.
 */
class SendPushOnMessageSent
{
    public function __construct(private PushNotificationService $push) {}

    public function handle(MessageSent $event): void
    {
        $message = $event->message->loadMissing(['sender', 'receiver']);
        $receiver = $message->receiver;
        if (! $receiver) {
            return;
        }

        $this->push->sendToUser(
            $receiver,
            $message->sender->name ?? 'Pesan baru',
            Str::limit($message->message, 100),
            [
                'type' => 'chat',
                'sender_id' => (string) $message->sender_id,
            ],
        );
    }
}
