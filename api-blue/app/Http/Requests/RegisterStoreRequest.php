<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'profile_picture' => 'required|image',
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required',
            'role' => 'required|in:buyer,store',
            'phone_number' => 'required|numeric'
        ];
    }

    public function attributes(): array
    {
        return [
            'profile_picture' => 'Foto Profil',
            'name' => 'Nama',
            'email' => 'Email',
            'password' => 'Kata Sandi',
            'role' => 'Peran',
            'phone_number' => 'Nomor Telepon'
        ];
    }
}
