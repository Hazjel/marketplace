<?php

return [
    // Sesuaikan dengan API key midtrans masing-masing
    // daftar midtrans di web midtrans, dafkarkan juga di .env
    'serverKey' => env('MIDTRANS_SERVER_KEY'),
    'isProduction' => env('MIDTRANS_IS_PRODUCTION', false),
    'isSanitized' => env('MIDTRANS_IS_SANITIZED', true),
    'is3ds' => env('MIDTRANS_IS_3DS', true),

    // Iris (payout): kunci API terpisah dari server key, dari portal Iris.
    'irisKey' => env('MIDTRANS_IRIS_KEY'),
    // `?:` bukan default env(): baris kosong di .env bernilai "", bukan null.
    'irisBaseUrl' => env('MIDTRANS_IRIS_BASE_URL') ?: (env('MIDTRANS_IS_PRODUCTION', false)
        ? 'https://app.midtrans.com/iris'
        : 'https://app.sandbox.midtrans.com/iris'),
];
