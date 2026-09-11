<?php

namespace App\Http\Resources;

use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // ProductVariantMongo casts price as decimal:2, which Laravel
            // returns as a string ("150000.00"). Variant price is whole
            // rupiah (Sprint B3.2a) — emit as an integer via the same
            // parser the domain uses (a fractional legacy value throws
            // rather than silently truncating). See
            // api-blue/docs/money-json-contract.md.
            'price' => Money::fromDecimalString((string) $this->price)->minor(),
            'stock' => $this->stock,
            'sku' => $this->sku,
            'image' => $this->image ? asset('storage/'.$this->image) : null,
            'variant_attributes' => $this->variant_attributes,
        ];
    }
}
