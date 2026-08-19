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
    /**
     * Deliberately no constructor, and no `Messaging`-typed property either
     * — Laravel's container resolves a class-typed constructor param via
     * resolveClass() regardless of nullability or a default value, and only
     * forgives a BindingResolutionException specifically. Kreait's service
     * provider throws its own Kreait\Firebase\Exception\RuntimeException
     * ("Unable to determine the Firebase Project ID") when no credentials
     * are configured (CI, or any environment without FIREBASE_CREDENTIALS),
     * which is NOT a BindingResolutionException — so even `?Messaging
     * $messaging = null` would still crash resolution of *anything*
     * depending on this service, including the listener that fires on
     * every transaction status change, even when no push would ever
     * actually be sent (e.g. the user has no device tokens). Resolving
     * app(Messaging::class) manually inside the method below, after the
     * empty-tokens fast path and inside the try/catch, keeps Firebase
     * entirely out of the picture until a send is genuinely about to
     * happen.
     */

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
            $report = app(Messaging::class)->sendMulticast($message, $tokens);
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
