<?php

return [
    /*
     * Platform fee yang dipotong dari setiap transaksi seller, dalam basis
     * points (1000 = 10%). Integer — dipakai App\ValueObjects\Money::percentage().
     * Bisa di-override via env ADMIN_FEE_BASIS_POINTS.
     */
    'admin_fee_basis_points' => (int) env('ADMIN_FEE_BASIS_POINTS', 1000),

    /*
     * Biaya layanan flat per checkout yang dibayar pembeli dan disimpan
     * platform (menutup biaya payment gateway). Rupiah bulat; tidak masuk
     * basis fee maupun bagian seller. Override via env BUYER_SERVICE_FEE.
     */
    'buyer_service_fee' => (int) env('BUYER_SERVICE_FEE', 1000),

    /*
     * Minimum jumlah penarikan saldo (dalam rupiah).
     * Default: 50000. Bisa di-override via env MIN_WITHDRAWAL_AMOUNT.
     */
    'min_withdrawal_amount' => (int) env('MIN_WITHDRAWAL_AMOUNT', 50000),
];
