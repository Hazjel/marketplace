<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransactionStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Diterima demi kompatibilitas client lama, tetapi nilainya
            // diabaikan: TransactionRepository menurunkan pembeli dari sesi
            // dan toko dari produk yang dibeli. Dibiarkan nullable supaya web
            // dan mobile bisa berhenti mengirimnya tanpa jadi breaking change.
            'buyer_id' => 'nullable|exists:buyers,id',
            'store_id' => 'nullable|exists:stores,id',
            'address_id' => 'required|integer',
            'address' => 'required|string',
            'city' => 'required|string',
            'postal_code' => 'required|string',
            'dest_latitude' => 'nullable|numeric|between:-90,90',
            'dest_longitude' => 'nullable|numeric|between:-180,180',
            'shipping' => 'required|string',
            'shipping_type' => 'required|string',
            // Diterima demi kompatibilitas client lama tetapi diabaikan:
            // TransactionRepository menanyakan ulang harganya ke gateway
            // pengiriman. Yang dipercaya dari client hanya PILIHAN kurir
            // (shipping + shipping_type), bukan nominalnya.
            'shipping_cost' => 'nullable|numeric|min:0',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.qty' => 'required|integer|min:1',
            'voucher_code' => 'nullable|string|exists:vouchers,code',
        ];
    }

    public function attributes()
    {
        return [
            'buyer_id' => 'Pembeli',
            'store_id' => 'Toko',
            'address_id' => 'Alamat',
            'address' => 'Alamat',
            'city' => 'Kota',
            'postal_code' => 'Kode Pos',
            'shipping' => 'Pengiriman',
            'shipping_type' => 'Jenis Pengiriman',
        ];
    }
}
