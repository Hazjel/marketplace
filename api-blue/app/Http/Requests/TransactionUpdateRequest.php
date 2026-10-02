<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransactionUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tracking_number' => 'nullable|string',
            'delivery_proof' => 'nullable|image|mimes:png,jpg',
            // No 'completed': only the buyer's complete endpoint and the
            // auto-complete job finish an order, and both release the escrow.
            // Setting it here stranded the seller's pending balance for good.
            'delivery_status' => 'required|in:processing,delivering',
        ];
    }

    public function attributes()
    {
        return [
            'tracking_number' => 'Nomor Resi',
            'delivery_proof' => 'Bukti Pengiriman',
            'delivery_status' => 'Status Pengiriman',
        ];
    }
}
