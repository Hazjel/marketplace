<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductReviewStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'transaction_id' => 'required|string|exists:transactions,id',
            'product_id' => 'required|string|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string',
            'is_anonymous' => 'nullable|boolean',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,mp4,mov|max:10240'
        ];
    }

    public function attributes()
    {
        return [
            'transaction_id' => 'Transaksi',
            'product_id' => 'Produk',
            'rating' => 'Rating',
            'review' => 'Review'
        ];
    }
}
