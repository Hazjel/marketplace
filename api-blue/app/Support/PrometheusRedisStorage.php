<?php

namespace App\Support;

use Prometheus\Storage\Redis;

/**
 * Library membaca Summary dengan KEYS, dan collect() memanggilnya selalu, walau
 * aplikasi ini hanya memakai counter dan histogram. KEYS ditolak ACL Redis bersama
 * (dan O(N) di instance yang dipakai project lain), lalu menjatuhkan seluruh render
 * /metrics dengan TypeError. Tanpa Summary, tidak ada yang dilewatkan.
 */
class PrometheusRedisStorage extends Redis
{
    protected function collectSummaries(): array
    {
        return [];
    }
}
