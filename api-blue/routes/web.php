<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-mongo', function () {
    return [
        'extension_loaded' => extension_loaded('mongodb'),
        'class_exists' => class_exists('MongoDB\Driver\Manager'),
        'php_ini' => php_ini_loaded_file(),
    ];
});
