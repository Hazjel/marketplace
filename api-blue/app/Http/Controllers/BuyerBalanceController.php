<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Buyer;
use App\Models\BuyerBalanceHistory;
use App\Models\Transaction;
use App\ValueObjects\Money;
use Illuminate\Http\Request;

class BuyerBalanceController extends Controller
{
    /**
     * Saldo Blukios of the caller only: the buyer is resolved from the
     * session, never from a request parameter.
     */
    public function index(Request $request)
    {
        $request->validate(['per_page' => 'nullable|integer|min:1|max:50']);

        $buyer = Buyer::where('user_id', $request->user()->id)->first();
        if (! $buyer) {
            return ResponseHelper::jsonResponse(false, 'Unauthorized', null, 403);
        }

        try {
            $histories = BuyerBalanceHistory::where('buyer_id', $buyer->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate((int) $request->query('per_page', 10));

            $transactionCodes = Transaction::whereIn(
                'id',
                $histories->getCollection()
                    ->where('reference_type', (new Transaction)->getMorphClass())
                    ->pluck('reference_id')
            )->pluck('code', 'id');

            return ResponseHelper::success([
                'balance' => Money::fromDecimalString((string) $buyer->balance)->minor(),
                'histories' => [
                    'data' => $histories->getCollection()->map(fn (BuyerBalanceHistory $h) => [
                        'id' => $h->id,
                        'type' => $h->type,
                        'amount' => Money::fromDecimalString((string) $h->amount)->minor(),
                        'remarks' => $h->remarks,
                        'reference_code' => $transactionCodes[$h->reference_id] ?? null,
                        'created_at' => $h->created_at,
                    ])->values(),
                    'meta' => [
                        'current_page' => $histories->currentPage(),
                        'last_page' => $histories->lastPage(),
                        'per_page' => $histories->perPage(),
                        'total' => $histories->total(),
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::exceptionResponse($e);
        }
    }
}
