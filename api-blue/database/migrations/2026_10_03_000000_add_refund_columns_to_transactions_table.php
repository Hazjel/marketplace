<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refund state for orders a seller cancels after the buyer paid.
 *
 * refund_status: null (no refund owed) | processing (Midtrans refund queued)
 * | manual_required (bank VA or a failed API refund: the platform transfers
 * by hand to the account the buyer gives) | refunded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('refund_status')->nullable()->after('payment_status');
            $table->string('refund_method')->nullable()->after('refund_status');
            $table->decimal('refund_amount', 26, 2)->nullable()->after('refund_method');
            $table->string('refund_reason')->nullable()->after('refund_amount');
            $table->text('refund_note')->nullable()->after('refund_reason');
            $table->string('refund_bank_name')->nullable()->after('refund_note');
            // Encrypted cast: ciphertext is far longer than the number.
            $table->text('refund_account_number')->nullable()->after('refund_bank_name');
            $table->string('refund_account_name')->nullable()->after('refund_account_number');
            $table->timestamp('refunded_at')->nullable()->after('refund_account_name');

            $table->index('refund_status');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['refund_status']);
            $table->dropColumn([
                'refund_status',
                'refund_method',
                'refund_amount',
                'refund_reason',
                'refund_note',
                'refund_bank_name',
                'refund_account_number',
                'refund_account_name',
                'refunded_at',
            ]);
        });
    }
};
