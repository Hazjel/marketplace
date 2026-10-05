<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Money sent out through Midtrans Iris: a buyer refund or a seller withdrawal
 * (the payable). A payable has at most one payout that is not failed or
 * rejected: after a failure (wrong account, say) a new attempt is a new row.
 *
 * status: creating (row saved, Iris not yet called) | unknown (the create call
 * failed in a way that leaves it unclear whether Iris queued it) | then Iris's
 * own: queued | approved | processed | completed | failed | rejected.
 * reference_no stays null until Iris answers the create call.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('payable');
            $table->string('reference_no')->nullable()->unique();
            $table->decimal('amount', 26, 2);
            $table->string('bank_code');
            // Encrypted cast: ciphertext is far longer than the number.
            $table->text('account_number');
            $table->string('account_name');
            $table->string('status');
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });

        // Partial index: Schema builder cannot express the WHERE.
        DB::statement("CREATE UNIQUE INDEX payouts_payable_active_unique ON payouts (payable_type, payable_id) WHERE status NOT IN ('failed', 'rejected')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS payouts_payable_active_unique');
        Schema::dropIfExists('payouts');
    }
};
