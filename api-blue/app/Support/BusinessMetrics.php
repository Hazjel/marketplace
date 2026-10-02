<?php

namespace App\Support;

use App\Http\Middleware\PrometheusMetrics;
use Throwable;

/**
 * Business-path counters on /metrics next to the HTTP ones:
 * `api_business_events_total{event, detail}`.
 *
 * Events: order_created, payment_paid (detail = payment type),
 * payment_failed, webhook_rejected (detail = reason), refund_requested,
 * refund_done (detail = midtrans|manual), refund_manual_required.
 *
 * Same rules as PrometheusMetrics: never fail the caller when Redis is
 * down, and never touch a metrics backend in tests.
 */
final class BusinessMetrics
{
    public static function record(string $event, string $detail = ''): void
    {
        if (app()->environment('testing')) {
            return;
        }

        try {
            app(PrometheusMetrics::class)->registry()->getOrRegisterCounter(
                'api',
                'business_events_total',
                'Kejadian bisnis: pesanan, pembayaran, webhook, refund',
                ['event', 'detail']
            )->inc([$event, $detail]);
        } catch (Throwable $e) {
            try {
                report($e);
            } catch (Throwable) {
                // Nothing more to do; metrics are best effort.
            }
        }
    }
}
