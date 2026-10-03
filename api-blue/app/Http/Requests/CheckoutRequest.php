<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Multi-store checkout: one shipping address, one order per store. Each order
 * follows the same rules as TransactionStoreRequest; buyer, store and
 * shipping cost are derived server-side as there.
 */
class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'address_id' => 'required|integer',
            'address' => 'required|string',
            'city' => 'required|string',
            'postal_code' => 'required|string',
            'dest_latitude' => 'nullable|numeric|between:-90,90',
            'dest_longitude' => 'nullable|numeric|between:-180,180',
            'orders' => 'required|array|min:1|max:10',
            'orders.*.shipping' => 'required|string',
            'orders.*.shipping_type' => 'required|string',
            'orders.*.voucher_code' => 'nullable|string|exists:vouchers,code',
            'orders.*.products' => 'required|array|min:1',
            'orders.*.products.*.product_id' => 'required|exists:products,id',
            'orders.*.products.*.variant_id' => 'nullable|string',
            'orders.*.products.*.qty' => 'required|integer|min:1',
        ];
    }

    public function attributes(): array
    {
        return [
            'address_id' => 'Alamat',
            'address' => 'Alamat',
            'city' => 'Kota',
            'postal_code' => 'Kode Pos',
            'orders' => 'Pesanan',
            'orders.*.shipping' => 'Pengiriman',
            'orders.*.shipping_type' => 'Jenis Pengiriman',
        ];
    }
}
