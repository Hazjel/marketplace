<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShippingOptionsRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'store_id' => 'required|uuid',
            'address_id' => 'required',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|uuid',
            'products.*.qty' => 'required|numeric|min:1'
        ];
    }
}
