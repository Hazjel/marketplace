<?php

return [
    /*
     * Platform fee yang dipotong dari setiap transaksi seller.
     * Default: 10% (0.10). Bisa di-override via env ADMIN_FEE_PERCENTAGE.
     */
    'admin_fee_percentage' => (float) env('ADMIN_FEE_PERCENTAGE', 0.10),

    /*
     * Minimum jumlah penarikan saldo (dalam rupiah).
     * Default: 50000. Bisa di-override via env MIN_WITHDRAWAL_AMOUNT.
     */
    'min_withdrawal_amount' => (int) env('MIN_WITHDRAWAL_AMOUNT', 50000),
];
