<?php

namespace App\Http\Resources;

use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'buyer' => $this->buyer ? new BuyerResource($this->buyer) : null,
            'store' => $this->store ? new StoreResource($this->store) : null,
            'address_id' => $this->address_id,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'dest_latitude' => $this->dest_latitude,
            'dest_longitude' => $this->dest_longitude,
            'shipping' => $this->shipping,
            'shipping_type' => $this->shipping_type,
            // Money fields are whole rupiah (Sprint B3.2) — emit as integers
            // via the same parser the domain uses, not a raw (int) cast. A
            // fractional legacy value (pre-B3.2c discount_amount could be
            // "10000.50") must throw here rather than silently truncate to
            // "10000". See api-blue/docs/money-json-contract.md.
            'shipping_cost' => Money::fromDecimalString((string) $this->shipping_cost)->minor(),
            'tracking_number' => $this->tracking_number,
            'delivery_proof' => $this->delivery_proof,
            'delivery_status' => $this->delivery_status,
            'tax' => Money::fromDecimalString((string) $this->tax)->minor(),
            'grand_total' => Money::fromDecimalString((string) $this->grand_total)->minor(),
            'voucher_id' => $this->voucher_id,
            'voucher_code' => $this->voucher?->code,
            'discount_amount' => Money::fromDecimalString((string) ($this->discount_amount ?? 0))->minor(),
            'payment_status' => $this->payment_status,
            'snap_token' => $this->snap_token,
            'transaction_details' => TransactionDetailResource::collection($this->transactionDetails),
            'product_reviews' => ProductReviewResource::collection($this->whenLoaded('productReviews')),
            'created_at' => $this->created_at,
        ];
    }
}
