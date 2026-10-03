<?php

use App\Http\Controllers\SitemapController;
use App\Http\Middleware\PrometheusMetrics;
use Illuminate\Support\Facades\Route;
use Prometheus\RenderTextFormat;

Route::get('/', function () {
    return view('welcome');
});

// Served on the storefront domain: nginx routes /sitemap.xml here.
Route::get('/sitemap.xml', SitemapController::class);

// Diakses Prometheus lewat jaringan Docker internal (bukan lewat nginx publik) --
// scrape target di monitoring/prometheus.yml nunjuk langsung ke container:port
Route::get('/metrics', function (PrometheusMetrics $metrics) {
    $renderer = new RenderTextFormat;

    return response($renderer->render($metrics->registry()->getMetricFamilySamples()))
        ->header('Content-Type', RenderTextFormat::MIME_TYPE);
});
