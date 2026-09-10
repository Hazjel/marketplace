<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->type;
        $value = $this->value;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'store_id' => $this->store_id,
            'type' => $type,
            // A fixed value is whole rupiah; a percentage value is a rate
            // with up to 2 decimals. min_purchase / max_discount are whole
            // rupiah (Sprint B3.2c). See docs/money-json-contract.md.
            'value' => $type === 'fixed' ? (int) $value : (float) (string) $value,
            'min_purchase' => $this->min_purchase !== null ? (int) $this->min_purchase : null,
            'max_discount' => $this->max_discount !== null ? (int) $this->max_discount : null,
            'usage_limit' => $this->usage_limit,
            'usage_limit_per_buyer' => $this->usage_limit_per_buyer,
            // Redeemed-so-far count — sellers need this to gauge how close a
            // voucher is to its usage_limit, cheap enough to compute eagerly
            // since seller voucher lists are never large.
            'redeemed_count' => $this->redemptions()->count(),
            'starts_at' => $this->starts_at,
            'expires_at' => $this->expires_at,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
