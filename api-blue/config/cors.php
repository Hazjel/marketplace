<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],

    // FRONTEND_URLS (jamak, dipisah koma) dipakai kalau ada >1 domain FE
    // (blukios.store + seller.blukios.store) -- fallback ke FRONTEND_URL
    // tunggal (lama) kalau belum di-set / kosong, supaya mundur-kompatibel.
    'allowed_origins' => array_values(array_filter(
        array_map('trim', explode(',', env('FRONTEND_URLS') ?: env('FRONTEND_URL', 'http://localhost:5173')))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Socket-Id'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
