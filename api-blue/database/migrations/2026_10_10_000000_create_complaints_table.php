<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A buyer's complaint about a shipped order. One per order; while it is open
 * or escalated the order cannot be completed (escrow stays held).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transaction_id')->unique()->constrained('transactions')->restrictOnDelete();
            $table->string('reason');
            $table->text('description');
            $table->json('photos');
            $table->string('status')->default('open');
            $table->text('seller_response')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('deadline_at');
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'deadline_at']);
        });

        // SQLite cannot add a CHECK through ALTER TABLE; the controllers validate there.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE complaints ADD CONSTRAINT complaints_status_check CHECK (status IN ('open', 'escalated', 'approved', 'rejected', 'withdrawn'))");
            DB::statement("ALTER TABLE complaints ADD CONSTRAINT complaints_reason_check CHECK (reason IN ('not_received', 'damaged', 'wrong_item', 'other'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
