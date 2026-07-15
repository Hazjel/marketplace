<?php

namespace App\Http\Requests;

use App\Models\StoreBalance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawalStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'store_balance_id' => 'required|exists:store_balances,id',
            'amount' => [
                'required',
                'numeric',
                'min:'.config('marketplace.min_withdrawal_amount'),
                function ($attribute, $value, $fail) {
                    $storeBalance = StoreBalance::find($this->store_balance_id);

                    if ($storeBalance->balance < $value) {
                        $fail('Saldo tidak mencukupi');
                    }
                },
            ],
            'bank_account_name' => 'required|string',
            'bank_account_number' => 'required|string',
            'bank_name' => 'required|string|in:bca,mandiri,bni,bri',
        ];
    }

    public function attributes()
    {
        return [
            'store_balance_id' => 'Dompet Toko',
            'amount' => 'Nomimal',
            'bank_account_name' => 'Nama Pemilik Rekening',
            'bank_account_number' => 'Nomor Rekening',
            'bank_name' => 'Nama Bank',
        ];
    }
}
