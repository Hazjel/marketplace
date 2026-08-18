<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VoucherController extends Controller
{
    /**
     * Preview-validate a voucher code against a store + subtotal, without
     * redeeming it. The actual redemption row is only ever created
     * atomically inside TransactionRepository::create() at real checkout —
     * this endpoint exists purely so the buyer can see the discount before
     * committing to an order, and re-validates everything server-side again
     * at checkout time since a validate-then-checkout race is possible
     * (e.g. someone else exhausts the usage limit in between).
     */
    public function validateCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'store_id' => 'required|exists:stores,id',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $buyer = Auth::user()->buyer;
        if (! $buyer) {
            return ResponseHelper::jsonResponse(false, 'Akun pembeli tidak ditemukan', null, 403);
        }

        $voucher = Voucher::where('code', $request->code)->first();
        if (! $voucher) {
            return ResponseHelper::jsonResponse(false, 'Kode voucher tidak ditemukan', null, 404);
        }

        $result = $voucher->validateFor($buyer->id, $request->store_id, (float) $request->subtotal);

        if (! $result['valid']) {
            return ResponseHelper::jsonResponse(false, $result['message'], null, 422);
        }

        return ResponseHelper::jsonResponse(true, 'Voucher berlaku', [
            'voucher_id' => $voucher->id,
            'code' => $voucher->code,
            'discount_amount' => $result['discount_amount'],
        ], 200);
    }
}
