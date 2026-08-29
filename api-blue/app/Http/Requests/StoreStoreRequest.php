<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'name' => 'required|string|max:255',
            // 'image' (bukan cuma 'mimes') -- StoreRepository menyimpan file
            // ini apa adanya lewat ->store() (tidak lewat Intervention Image
            // seperti product image / ProcessProductImageJob), dan diserve
            // langsung sebagai file statis oleh nginx (location /storage) --
            // nginx sekarang menambahkan X-Content-Type-Options: nosniff
            // di situ juga (lihat docker/nginx/default.conf), karena
            // middleware SecurityHeaders Laravel tidak pernah menyentuh
            // file yang diserve langsung oleh nginx.
            //
            // 'image' di sini SENGAJA ditambahkan biar konsisten dengan
            // Request lain (ProductCategoryStoreRequest, dst) -- tapi
            // diverifikasi langsung ke vendor/laravel/framework: di versi
            // ini validateImage() cuma delegasi ke validateMimes() dengan
            // daftar {jpg,jpeg,png,gif,bmp,webp}, BUKAN getimagesize().
            // Jadi menambahkan 'image' di sini TIDAK menutup celah baru --
            // 'mimes:png,jpg' saja sudah cukup, karena validateMimes()
            // menebak tipe file lewat guessExtension() yang membaca isi
            // file (finfo), bukan percaya nama/ekstensi yang diklaim client.
            'logo' => 'required|image|mimes:png,jpg|max:2048',
            'about' => 'required|string',
            'phone' => 'required|string',
            'address_id' => 'required',
            'city' => 'required|string',
            'address' => 'required|string',
            'postal_code' => 'required|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ];
    }

    public function attributes()
    {
        return [
            'user_id' => 'User',
            'name' => 'Nama Toko',
            'logo' => 'Logo Toko',
            'about' => 'Tentang Toko',
            'phone' => 'Nomor Telepon',
            'address_id' => 'Alamat Toko',
            'city' => 'Kota',
            'address' => 'Alamat',
            'postal_code' => 'Kode Pos',
        ];
    }
}
