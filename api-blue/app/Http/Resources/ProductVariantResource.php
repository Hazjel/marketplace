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
            'price' => $this->price,
            'stock' => $this->stock,
            'sku' => $this->sku,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'variant_attributes' => $this->variant_attributes,
        ];
    }
}
