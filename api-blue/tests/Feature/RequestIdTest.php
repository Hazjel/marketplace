<?php

namespace Tests\Feature;

use App\Helpers\ResponseHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Formatter\JsonFormatter;
use RuntimeException;
use Tests\TestCase;

class RequestIdTest extends TestCase
{
    public function test_every_response_carries_a_request_id(): void
    {
        $response = $this->getJson('/api/health');

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f-]{36}$/',
            (string) $response->headers->get('X-Request-Id')
        );
        $this->assertNotSame(
            $response->headers->get('X-Request-Id'),
            $this->getJson('/api/health')->headers->get('X-Request-Id')
        );
    }

    public function test_a_well_formed_incoming_id_is_kept_and_anything_else_replaced(): void
    {
        $this->getJson('/api/health', ['X-Request-Id' => 'retry-abc-12345'])
            ->assertHeader('X-Request-Id', 'retry-abc-12345');

        $forged = $this->getJson('/api/health', ['X-Request-Id' => "bad id\n{\"level\":\"ERROR\"}"]);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $forged->headers->get('X-Request-Id'));
    }

    public function test_an_error_response_logs_and_returns_the_same_id(): void
    {
        $path = storage_path('logs/request-id-test.log');
        @unlink($path);
        config(['logging.channels.json_test' => [
            'driver' => 'single',
            'path' => $path,
            'formatter' => JsonFormatter::class,
        ]]);
        config(['logging.default' => 'json_test']);

        Route::get('api/_test/fails', function () {
            // A plain line that never mentions the id: it must still carry it.
            Log::info('charging the card');

            return ResponseHelper::exceptionResponse(new RuntimeException('database exploded'));
        });

        $response = $this->getJson('/api/_test/fails')->assertStatus(500);
        $id = $response->headers->get('X-Request-Id');

        $entries = array_map(
            fn (string $line) => json_decode($line, true),
            array_filter(explode("\n", (string) file_get_contents($path)))
        );
        $error = collect($entries)->firstWhere('message', 'Unhandled exception');

        $this->assertNotNull($error, 'the exception was not logged as JSON');
        $this->assertSame('ERROR', $error['level_name']);
        $this->assertSame('database exploded', $error['context']['message']);
        $this->assertStringContainsString((string) $id, json_encode($error));

        // Context puts the id on every line of the request, so `grep <id>`
        // finds them all.
        $plain = collect($entries)->firstWhere('message', 'charging the card');
        $this->assertNotNull($plain);
        $this->assertSame($id, $plain['extra']['request_id'] ?? $plain['context']['request_id'] ?? null);

        Log::forgetChannel('json_test');
        @unlink($path);
    }
}
