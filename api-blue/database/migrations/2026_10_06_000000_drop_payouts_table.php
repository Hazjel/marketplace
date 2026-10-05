<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The Midtrans Iris payout integration was dropped before it was ever
 * switched on; production already created this (always empty) table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('payouts');
    }

    public function down(): void
    {
        // Nothing to restore: the table never held data.
    }
};
