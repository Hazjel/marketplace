<?php

namespace App\Console\Commands;

use App\Interfaces\TransactionRepositoryInterface;
use App\Models\Complaint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EscalateComplaints extends Command
{
    protected $signature = 'complaints:escalate';

    protected $description = 'Escalate open complaints the seller did not answer within 2 days to admin.';

    public function handle(TransactionRepositoryInterface $transactionRepository): void
    {
        // The next run (15 min) picks up the rest.
        $ids = Complaint::where('status', 'open')->where('deadline_at', '<=', now())
            ->orderBy('deadline_at')->limit(200)->pluck('id');

        foreach ($ids as $id) {
            try {
                // from ['open'] under the lock: a seller who answered meanwhile wins.
                $transactionRepository->moveComplaint($id, ['open'], 'escalated', ['seller_response' => null]);
                $this->info("Escalated complaint {$id}");
            } catch (\Throwable $e) {
                // 422: the seller or buyer acted first.
                if ($e->getCode() === 422) {
                    Log::info('complaints:escalate skipped', ['complaint' => $id, 'error' => $e->getMessage()]);
                } else {
                    Log::error('complaints:escalate failed', ['complaint' => $id, 'error' => $e->getMessage()]);
                }
            }
        }
    }
}
