<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class LogHelper
{
    /**
     * Log an API request action (for auditing important actions).
     */
    public static function action(string $action, array $context = []): void
    {
        Log::channel('daily')->info("[ACTION] {$action}", array_merge([
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
            'timestamp' => now()->toIso8601String(),
        ], $context));
    }

    /**
     * Log a payment-related event.
     */
    public static function payment(string $event, array $context = []): void
    {
        Log::channel('daily')->info("[PAYMENT] {$event}", array_merge([
            'user_id' => auth()->id(),
            'timestamp' => now()->toIso8601String(),
        ], $context));
    }

    /**
     * Log a security-related event (failed auth, suspicious activity).
     */
    public static function security(string $event, array $context = []): void
    {
        Log::channel('daily')->warning("[SECURITY] {$event}", array_merge([
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ], $context));
    }

    /**
     * Log an error with full context.
     */
    public static function error(string $message, \Throwable $e, array $context = []): void
    {
        Log::channel('daily')->error("[ERROR] {$message}", array_merge([
            'exception' => get_class($e),
            'error_message' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine(),
            'user_id' => auth()->id(),
            'url' => request()->fullUrl(),
            'timestamp' => now()->toIso8601String(),
        ], $context));
    }
}
