<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariantMongo;
use App\Rules\CompareAtPrice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ProductUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
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
                    if ($category && $category->parent_id === null) {
                        $fail('Kategori produk harus memiliki kategori induk.');
                    }
                },
            ],
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'condition' => 'required|string|in:new,second',
            // Whole rupiah only — see ProductStoreRequest / money-contract.md.
            'price' => 'required|integer|min:0|max:'.Product::MAX_PRICE,
            // Display only; checkout always charges price.
            'compare_at_price' => ['nullable', 'integer', 'min:1', 'max:'.Product::MAX_PRICE, new CompareAtPrice],
            'weight' => 'required|numeric|min:0|max:999.99',
            'stock' => 'required|integer|min:0',
            'product_images' => 'nullable|array',
            'product_images.*.image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            'product_images.*.is_thumbnail' => 'required|boolean',
            'variants' => 'nullable|array',
            'variants.*.id' => [
                'bail',
                'nullable',
                'string',
                'regex:/\A[a-f0-9]{24}\z/i',
                function ($attribute, $value, $fail) {
                    if (! ProductVariantMongo::where('product_id', (string) $this->route('product'))
                        ->whereKey($value)->exists()) {
                        $fail('Varian tidak ditemukan pada produk ini.');
                    }
                },
            ],
            'variants.*.name' => 'required_with:variants|string',
            'variants.*.price' => 'required_with:variants|integer|min:0|max:'.Product::MAX_PRICE,
            'variants.*.compare_at_price' => ['nullable', 'integer', 'min:1', 'max:'.Product::MAX_PRICE, new CompareAtPrice],
            'variants.*.stock' => 'required_with:variants|integer|min:0',
            'variants.*.sku' => 'nullable|string',
            'variants.*.variant_attributes' => 'nullable|array',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'price.max' => 'Harga maksimal Rp1.000.000.000.000.',
            'variants.*.price.max' => 'Harga maksimal Rp1.000.000.000.000.',
            'compare_at_price.max' => 'Harga sebelum diskon maksimal Rp1.000.000.000.000.',
            'variants.*.compare_at_price.max' => 'Harga sebelum diskon maksimal Rp1.000.000.000.000.',
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
            'compare_at_price' => 'Harga sebelum diskon',
            'variants.*.compare_at_price' => 'Harga sebelum diskon',
            'weight' => 'Berat',
            'stock' => 'Stok',
            'product_images.*.image' => 'Gambar',
            'product_images.*.is_thumbnail' => 'Gambar Utama',
        ];
    }

    public function prepareForValidation()
    {
        $user = Auth::user();

        if ($user && ! $user->hasRole('admin') && $user->store) {
            $this->merge(['store_id' => $user->store->id]);
        }

        $this->mergeStoredCompareAtPrices();
    }

    /**
     * A compare_at_price left out of the request is kept (older clients do not
     * send it), so validate the stored one against the new price. An explicit
     * null still clears it. The cheapest variant's value is copied to the
     * product, so only a product without variants checks its own.
     */
    private function mergeStoredCompareAtPrices(): void
    {
        $product = Product::find((string) $this->route('product'));
        if (! $product) {
            return;
        }

        $variants = $this->input('variants');
        if (empty($variants) && ! $this->has('compare_at_price') && $product->compare_at_price !== null) {
            $this->merge(['compare_at_price' => (int) $product->compare_at_price]);
        }

        if (! is_array($variants) || $variants === []) {
            return;
        }

        $stored = ProductVariantMongo::where('product_id', (string) $product->id)->get()
            ->keyBy(fn ($v) => (string) $v->id);
        foreach ($variants as $i => $variant) {
            $id = is_array($variant) ? ($variant['id'] ?? null) : null;
            if (is_string($id) && ! array_key_exists('compare_at_price', $variant)
                && $stored->has($id) && $stored[$id]->compare_at_price !== null) {
                $variants[$i]['compare_at_price'] = (int) (string) $stored[$id]->compare_at_price;
            }
        }
        $this->merge(['variants' => $variants]);
    }
}
