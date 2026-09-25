<?php

namespace Tests\Unit;

use App\Http\Middleware\PrometheusMetrics;
use App\Support\PrometheusRedisStorage;
use Prometheus\Storage\AbstractRedis;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

/**
 * ACL Redis bersama menolak key di luar namespace aplikasi dengan NOPERM, dan
 * prefix bawaan library (`PROMETHEUS_`) memang di luar namespace itu. Middleware-nya
 * menelan errornya supaya request tetap jalan, jadi tanpa test ini metrik bisa
 * berhenti terkumpul dan tidak ada yang tahu.
 */
class PrometheusMetricsTest extends TestCase
{
    public function test_prefix_penyimpanan_berada_di_dalam_namespace_redis_aplikasi(): void
    {
        config(['database.redis.options.prefix' => 'blukios:']);

        $this->assertSame('blukios:prometheus:', (new PrometheusMetrics)->storagePrefix());
    }

    public function test_registry_memasang_prefix_itu_ke_library(): void
    {
        config(['database.redis.options.prefix' => 'blukios:']);

        (new PrometheusMetrics)->registry();

        $prefix = (new ReflectionProperty(AbstractRedis::class, 'prefix'))->getValue();
        $this->assertSame('blukios:prometheus:', $prefix);
    }

    public function test_membaca_summary_tidak_menyentuh_redis(): void
    {
        // Storage dibuat tanpa koneksi; kalau collectSummaries() memanggil KEYS,
        // test ini gagal karena mencoba terhubung ke Redis yang tidak ada.
        $storage = new PrometheusRedisStorage(['host' => '203.0.113.1', 'timeout' => 0.01]);

        $result = (new ReflectionMethod($storage, 'collectSummaries'))->invoke($storage);

        $this->assertSame([], $result);
    }
}
