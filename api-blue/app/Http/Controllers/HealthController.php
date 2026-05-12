<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    public function __invoke()
    {
        $health = [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
            'services' => [],
        ];

        // Check database
        try {
            DB::connection()->getPdo();
            $health['services']['database'] = 'connected';
        } catch (\Exception $e) {
            $health['services']['database'] = 'disconnected';
            $health['status'] = 'degraded';
        }

        // Check cache
        try {
            Cache::put('health_check', true, 10);
            $health['services']['cache'] = Cache::get('health_check') ? 'working' : 'failed';
        } catch (\Exception $e) {
            $health['services']['cache'] = 'failed';
            $health['status'] = 'degraded';
        }

        $statusCode = $health['status'] === 'ok' ? 200 : 503;

        return response()->json($health, $statusCode);
    }
}
