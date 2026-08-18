<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('voucher_id');
            $table->foreign('voucher_id')->references('id')->on('vouchers')->onDelete('cascade');
            $table->uuid('buyer_id');
            $table->foreign('buyer_id')->references('id')->on('buyers')->onDelete('cascade');
            // Nullable in the schema, but in practice always set atomically at
            // transaction creation — the validate-only preview endpoint never
            // creates a row here, only actual checkout does.
            $table->uuid('transaction_id')->nullable();
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');
            $table->timestamp('redeemed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_redemptions');
    }
};
