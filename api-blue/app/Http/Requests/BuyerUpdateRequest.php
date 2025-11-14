<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BuyerUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'profile_picture' => 'nullable|image|mimes:png,jpg',
            'phone_number' => 'required|string',
        ];
    }

    public function attributes()
    {
        return [
            'profile_picture' => 'Avatar',
            'phone_number' => 'Nomor HP',
        ];
    }
}
