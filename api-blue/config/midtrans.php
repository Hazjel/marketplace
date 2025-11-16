<?php

return [
    // Sesuaikan dengan API key midtrans masing-masing
    // daftar midtrans di web midtrans, dafkarkan juga di .env
    'serverKey' => env('MIDTRANS_SERVER_KEY'),
    'isProducstion' => env('MIDTRANS_IS_PRODUCTION', false),
    'isSanitized' => env('MIDTRANS_IS_SANITIZED', true),
    'is3ds' => env('MIDTRANS_IS_3DS', true),
];
