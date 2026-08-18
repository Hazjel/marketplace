<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\VoucherResource;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Seller-facing voucher CRUD, scoped to the caller's own store —
 * deliberately separate from VoucherController (buyer-facing preview/
 * validate) rather than one controller wearing both hats. A seller here
 * can only ever create/edit vouchers with store_id = their own store;
 * platform-wide vouchers (store_id null) stay admin/DB-only, since those
 * affect every store's checkout, not just the caller's.
 */
class SellerVoucherController extends Controller
{
    private function rules(?string $voucherId = null): array
    {
        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('vouchers', 'code')->ignore($voucherId),
            ],
            'type' => 'required|in:fixed,percentage',
            'value' => 'required|numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_buyer' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'sometimes|boolean',
        ];
    }

    private function messages(): array
    {
        return [
            'code.unique' => 'Kode voucher ini sudah dipakai.',
            'expires_at.after_or_equal' => 'Tanggal berakhir harus setelah tanggal mulai.',
        ];
    }

    private function myStoreId(): ?string
    {
        return Auth::user()->store?->id;
    }

    public function index(Request $request)
    {
        $storeId = $this->myStoreId();
        if (! $storeId) {
            return ResponseHelper::jsonResponse(false, 'Anda belum memiliki toko', null, 403);
        }

        $vouchers = Voucher::where('store_id', $storeId)
            ->orderBy('created_at', 'desc')
            ->get();

        return ResponseHelper::jsonResponse(true, 'Data Voucher Berhasil Diambil', VoucherResource::collection($vouchers), 200);
    }

    public function store(Request $request)
    {
        $storeId = $this->myStoreId();
        if (! $storeId) {
            return ResponseHelper::jsonResponse(false, 'Anda belum memiliki toko', null, 403);
        }

        // Normalize BEFORE validating, not after — otherwise the unique
        // check runs against the raw-case input while the stored value is
        // uppercased, so a same-code-different-case duplicate (e.g. buyer
        // types "dupe10" when "DUPE10" already exists) sails past validation
        // and blows up as an uncaught DB constraint violation at insert time
        // instead of a clean 422.
        $this->normalizeCode($request);
        $data = $request->validate($this->rules(), $this->messages());

        $voucher = Voucher::create([
            ...$data,
            'store_id' => $storeId,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return ResponseHelper::jsonResponse(true, 'Voucher Berhasil Dibuat', new VoucherResource($voucher), 201);
    }

    public function update(Request $request, string $id)
    {
        $storeId = $this->myStoreId();
        $voucher = Voucher::find($id);

        if (! $voucher || $voucher->store_id !== $storeId) {
            return ResponseHelper::jsonResponse(false, 'Voucher tidak ditemukan', null, 404);
        }

        $this->normalizeCode($request);
        $data = $request->validate($this->rules($voucher->id), $this->messages());

        $voucher->update($data);

        return ResponseHelper::jsonResponse(true, 'Voucher Berhasil Diupdate', new VoucherResource($voucher), 200);
    }

    private function normalizeCode(Request $request): void
    {
        if ($request->filled('code')) {
            $request->merge(['code' => strtoupper($request->input('code'))]);
        }
    }

    public function destroy(string $id)
    {
        $storeId = $this->myStoreId();
        $voucher = Voucher::find($id);

        if (! $voucher || $voucher->store_id !== $storeId) {
            return ResponseHelper::jsonResponse(false, 'Voucher tidak ditemukan', null, 404);
        }

        // voucher_redemptions.voucher_id cascades on delete — deleting a
        // voucher that's already been used would wipe its redemption
        // history (transactions themselves keep their discount_amount
        // snapshot regardless, but the audit trail of who/when redeemed
        // it would be gone). Force deactivate instead once it has history.
        if ($voucher->redemptions()->exists()) {
            return ResponseHelper::jsonResponse(
                false,
                'Voucher sudah pernah dipakai — nonaktifkan saja, jangan dihapus, supaya riwayat penggunaannya tidak hilang',
                null,
                422
            );
        }

        $voucher->delete();

        return ResponseHelper::jsonResponse(true, 'Voucher Berhasil Dihapus', null, 200);
    }
}
