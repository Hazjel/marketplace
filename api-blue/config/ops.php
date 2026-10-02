<?php

/*
| Operational alerts sent by `ops:check` (scheduled every 5 minutes).
|
| The shared ops Prometheus stack is not reliably running, so Blukios checks
| its own critical paths and emails a person when one breaks. Without
| OPS_ALERT_EMAIL the problems are only logged.
*/

return [
    'alert_email' => env('OPS_ALERT_EMAIL'),

    // The same problem is mailed at most once per this many minutes.
    'alert_cooldown_minutes' => (int) env('OPS_ALERT_COOLDOWN_MINUTES', 60),

    // The scheduler queues a heartbeat job every minute; no heartbeat for
    // this long means no queue worker is consuming jobs (refunds, emails).
    'queue_stale_minutes' => (int) env('OPS_QUEUE_STALE_MINUTES', 10),

    // RefundCancelledTransactionJob retries for ~1.8 days, then falls back
    // to manual. A refund still "processing" after this lost its job.
    'refund_processing_max_hours' => (int) env('OPS_REFUND_PROCESSING_MAX_HOURS', 48),

    // Manual refunds waiting on a transfer are reminded once a day.
    'manual_refund_reminder_minutes' => 24 * 60,
];
