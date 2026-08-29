<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // ProductVariantMongo casts price sebagai 'decimal:2' -- Laravel
            // decimal cast SELALU balik sebagai string (asDecimal()), bukan
            // angka. Tanpa normalisasi ini payload jadi "150000.00" (string),
            // sama seperti bug yang sudah ditutup di ProductResource untuk
            // products.price -- klien (mobile int.tryParse('150000.00')
            // gagal parse -> null -> fallback 0) diam-diam menampilkan
            // harga/stok salah.
            'price' => (float) (string) $this->price,
            'stock' => $this->stock,
            'sku' => $this->sku,
            'image' => $this->image ? asset('storage/'.$this->image) : null,
            'variant_attributes' => $this->variant_attributes,
        ];
    }
}
