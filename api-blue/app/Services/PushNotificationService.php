<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Throwable;

/**
 * Thin wrapper around the Firebase Admin SDK's messaging component — the
 * only place in the app that talks to Firebase directly. Callers never
 * touch Kreait\Firebase types, just sendToUser().
 */
class PushNotificationService
{
    public function __construct(private Messaging $messaging) {}

    /**
     * Sends the same notification to every device this user is currently
     * registered on. Silent no-op if they have none (never registered, or
     * notifications disabled) — this is a best-effort side channel, never
     * something a caller should have to handle failure for.
     *
     * $data values are cast to strings — FCM's data payload only supports
     * string values.
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        $tokens = DeviceToken::where('user_id', $user->id)->pluck('token')->all();
        if (empty($tokens)) {
            return;
        }

        $message = CloudMessage::new()
            ->withNotification(FirebaseNotification::create($title, $body))
            ->withData(array_map('strval', $data));

        try {
            $report = $this->messaging->sendMulticast($message, $tokens);
        } catch (Throwable $e) {
            // Never let a push failure break the caller's own request (e.g.
            // a transaction status update) — this is a side effect, not the
            // point of the request.
            Log::warning('Push notification send failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        // Prune tokens Firebase says are dead (app uninstalled, token
        // rotated without us hearing about it via onTokenRefresh yet) so
        // future sends stop retrying them.
        foreach ($report->invalidTokens() as $invalidToken) {
            DeviceToken::where('token', $invalidToken)->delete();
        }
    }
}
