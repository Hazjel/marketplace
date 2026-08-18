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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            // Null = platform-wide voucher, usable at any store. Non-null
            // restricts it to that one store's products.
            $table->uuid('store_id')->nullable();
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->enum('type', ['fixed', 'percentage']);
            $table->decimal('value', 26, 2);
            $table->decimal('min_purchase', 26, 2)->nullable();
            // Caps the discount for percentage-type vouchers only; ignored for fixed.
            $table->decimal('max_discount', 26, 2)->nullable();
            // Null = unlimited.
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_buyer')->nullable()->default(1);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
