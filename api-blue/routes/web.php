<?php

use App\Http\Middleware\PrometheusMetrics;
use Illuminate\Support\Facades\Route;
use Prometheus\RenderTextFormat;

Route::get('/', function () {
    return view('welcome');
});

// Diakses Prometheus lewat jaringan Docker internal (bukan lewat nginx publik) --
// scrape target di monitoring/prometheus.yml nunjuk langsung ke container:port
Route::get('/metrics', function (PrometheusMetrics $metrics) {
    $renderer = new RenderTextFormat;

    return response($renderer->render($metrics->registry()->getMetricFamilySamples()))
        ->header('Content-Type', RenderTextFormat::MIME_TYPE);
});

Route::get('/test-mongo', function () {
    return [
        'extension_loaded' => extension_loaded('mongodb'),
        'class_exists' => class_exists('MongoDB\Driver\Manager'),
        'php_ini' => php_ini_loaded_file(),
    ];
});

Route::get('/storage/{path}', function ($path) {
    if (file_exists(public_path('storage/'.$path))) {
        return response()->file(public_path('storage/'.$path));
    }
    abort(404);
})->where('path', '.*');
