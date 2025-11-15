<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\ProductCategory;

class ProductUpdateRequest extends FormRequest
{
    

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'store_id' => 'required|exists:stores,id',
            'product_category_id' => [
                'required',
                'exists:product_categories,id',
                function ($attribute, $value, $fail) {
                    $category = ProductCategory::find($value);
                    if ($category && $category->parent_id === null)
                    {
                        $fail('Kategori produk harus memiliki kategori induk.');
                    }
                }
            ],
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'condition' => 'required|string|in:new,second',
            'price' => 'required|numeric|min:0',
            'weight' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'product_images' => 'nullable|array',
            'product_images.*.image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            'product_images.*.is_thumbnail' => 'required|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'store_id' => 'Toko', 
            'product_category_id' => 'Kategori Produk',
            'name' => 'Nama', 
            'description' => 'Deskripsi',
            'condition' => 'Kondisi',
            'price' => 'Harga',
            'weight' => 'Berat',
            'stock' => 'Stok',
            'product_images.*.image' => 'Gambar',
            'product_images.*.is_thumbnail' => 'Gambar Utama',
        ];
    }
}
