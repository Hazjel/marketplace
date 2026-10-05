<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use App\Services\IrisPayoutGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class IrisController extends Controller
{
    private const TERMINAL_STATUSES = ['completed', 'failed', 'rejected'];

    /**
     * Notifikasi status payout dari Iris. Format signature-nya belum kita
     * verifikasi, jadi body hanya dipakai untuk reference_no: status diambil
     * ulang dari Iris dengan kunci kita. Notifikasi palsu paling jauh memicu
     * satu lookup, tidak bisa menandai payout selesai.
     *
     * Notifikasi bisa datang tidak berurutan; status terminal tidak pernah
     * ditimpa, jadi lookup lama yang selesai belakangan tidak memundurkannya.
     */
    public function notification(Request $request, IrisPayoutGateway $iris)
    {
        $referenceNo = $request->input('reference_no');
        $referenceNo = is_string($referenceNo) ? $referenceNo : '';
        $logRef = mb_substr($referenceNo, 0, 64);

        $payout = $referenceNo === '' ? null : Payout::where('reference_no', $referenceNo)->first();

        if (! $payout) {
            Log::warning('Iris notification untuk payout yang tidak dikenal', ['reference_no' => $logRef]);

            return response()->json(['message' => 'OK']);
        }

        try {
            $detail = $iris->payoutDetail($referenceNo);
            $status = $detail['status'] ?? null;

            if (! is_string($status) || $status === '') {
                throw new RuntimeException('Iris payout detail tanpa status');
            }
        } catch (Throwable $e) {
            Log::error('Iris notification: lookup payout gagal', [
                'reference_no' => $logRef,
                'error' => $e->getMessage(),
            ]);

            // 5xx supaya Iris mengirim ulang.
            return response()->json(['message' => 'Payout lookup failed'], 503);
        }

        DB::transaction(function () use ($payout, $detail, $status, $logRef) {
            $payout = Payout::whereKey($payout->id)->lockForUpdate()->first();

            if ($payout->status === $status) {
                return;
            }

            if (in_array($payout->status, self::TERMINAL_STATUSES, true)) {
                Log::warning('Iris notification: payout sudah final, status baru diabaikan', [
                    'reference_no' => $logRef,
                    'current' => $payout->status,
                    'iris' => $status,
                ]);

                return;
            }

            $payout->update([
                'status' => $status,
                'failure_reason' => is_string($detail['error_message'] ?? null) ? $detail['error_message'] : null,
            ]);
        });

        return response()->json(['message' => 'OK']);
    }
}
