<?php

namespace Tests\Feature;

use App\Http\Middleware\IdempotencyMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Middleware ini dulu memeriksa Cache::has, memproses, lalu Cache::put.
 * Tiga langkah terpisah: dua request bersamaan sama-sama melihat kunci kosong
 * dan sama-sama lolos, sehingga satu checkout bisa jadi dua transaksi.
 */
class IdempotencyTest extends TestCase
{
    private function requestWithKey(?string $key): Request
    {
        $request = Request::create('/api/transaction', 'POST');

        if ($key !== null) {
            $request->headers->set('X-Idempotency-Key', $key);
        }

        return $request;
    }

    private function jsonResponse(array $body, int $status)
    {
        return response()->json($body, $status);
    }

    public function test_missing_key_is_rejected(): void
    {
        $response = (new IdempotencyMiddleware)->handle(
            $this->requestWithKey(null),
            fn () => $this->jsonResponse(['success' => true], 201)
        );

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_first_call_passes_through_and_is_remembered(): void
    {
        $response = (new IdempotencyMiddleware)->handle(
            $this->requestWithKey('KEY-1'),
            fn () => $this->jsonResponse(['success' => true, 'data' => ['id' => 'trx-1']], 201)
        );

        $this->assertSame(201, $response->getStatusCode());
        $this->assertNotNull(Cache::get('idempotency::KEY-1'));
    }

    public function test_repeat_of_a_finished_call_is_replayed_without_running_again(): void
    {
        $middleware = new IdempotencyMiddleware;
        $calls = 0;
        $handler = function () use (&$calls) {
            $calls++;

            return $this->jsonResponse(['success' => true, 'data' => ['id' => 'trx-1']], 201);
        };

        $middleware->handle($this->requestWithKey('KEY-2'), $handler);
        $second = $middleware->handle($this->requestWithKey('KEY-2'), $handler);

        $this->assertSame(1, $calls, 'handler tidak boleh dijalankan dua kali');
        $this->assertSame(201, $second->getStatusCode());
        $this->assertSame('true', $second->headers->get('X-Idempotency-Replayed'));
    }

    public function test_a_second_request_arriving_mid_flight_is_refused(): void
    {
        $middleware = new IdempotencyMiddleware;
        $inner = 0;

        // Handler pertama belum selesai ketika request kedua masuk — persis
        // jendela balapan yang dulu meloloskan keduanya.
        $response = $middleware->handle(
            $this->requestWithKey('KEY-3'),
            function () use ($middleware, &$inner) {
                $inner = $middleware->handle(
                    $this->requestWithKey('KEY-3'),
                    fn () => $this->jsonResponse(['success' => true], 201)
                )->getStatusCode();

                return $this->jsonResponse(['success' => true, 'data' => ['id' => 'trx-1']], 201);
            }
        );

        $this->assertSame(409, $inner, 'request kedua harus ditolak, bukan ikut memproses');
        $this->assertSame(201, $response->getStatusCode());
    }

    public function test_a_failed_call_can_be_retried_with_the_same_key(): void
    {
        $middleware = new IdempotencyMiddleware;

        $first = $middleware->handle(
            $this->requestWithKey('KEY-4'),
            fn () => $this->jsonResponse(['success' => false], 500)
        );
        $this->assertSame(500, $first->getStatusCode());

        $second = $middleware->handle(
            $this->requestWithKey('KEY-4'),
            fn () => $this->jsonResponse(['success' => true, 'data' => ['id' => 'trx-1']], 201)
        );

        $this->assertSame(201, $second->getStatusCode(), 'kegagalan tidak boleh mengunci kuncinya');
    }

    public function test_an_exception_releases_the_key(): void
    {
        $middleware = new IdempotencyMiddleware;

        try {
            $middleware->handle(
                $this->requestWithKey('KEY-5'),
                fn () => throw new \RuntimeException('boom')
            );
            $this->fail('exception seharusnya diteruskan');
        } catch (\RuntimeException) {
            // diharapkan
        }

        $this->assertNull(Cache::get('idempotency::KEY-5'), 'kunci harus dilepas');

        $retry = $middleware->handle(
            $this->requestWithKey('KEY-5'),
            fn () => $this->jsonResponse(['success' => true], 201)
        );
        $this->assertSame(201, $retry->getStatusCode());
    }

    public function test_keys_cached_by_the_previous_implementation_are_still_replayed(): void
    {
        // Entri lama: body disimpan di level atas, tanpa 'state'. Kunci yang
        // sudah beredar tidak boleh mendadak diproses ulang setelah deploy.
        Cache::put('idempotency::KEY-6', ['success' => true, 'data' => ['id' => 'lama'], 'code' => 201], now()->addHour());

        $calls = 0;
        $response = (new IdempotencyMiddleware)->handle(
            $this->requestWithKey('KEY-6'),
            function () use (&$calls) {
                $calls++;

                return $this->jsonResponse(['success' => true], 201);
            }
        );

        $this->assertSame(0, $calls);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('true', $response->headers->get('X-Idempotency-Replayed'));
    }
}
