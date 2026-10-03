<?php

namespace App\Http\Resources;

use App\Models\Transaction;
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
        /** @var Transaction $transaction */
        $transaction = $this->resource;

        return [
            'id' => $this->id,
            'code' => $this->code,
            // Shared by every order of one multi-store checkout; null otherwise.
            'payment_code' => $transaction->payment_code,
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
            'service_fee' => Money::fromDecimalString((string) ($this->service_fee ?? 0))->minor(),
            'grand_total' => Money::fromDecimalString((string) $this->grand_total)->minor(),
            'voucher_id' => $this->voucher_id,
            'voucher_code' => $this->voucher?->code,
            'discount_amount' => Money::fromDecimalString((string) ($this->discount_amount ?? 0))->minor(),
            'payment_status' => $this->payment_status,
            ...$this->refund(),
            'refund_account' => $this->refundAccount($request),
            'snap_token' => $this->snap_token,
            'transaction_details' => TransactionDetailResource::collection($this->transactionDetails),
            'product_reviews' => ProductReviewResource::collection($this->whenLoaded('productReviews')),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function refund(): array
    {
        /** @var Transaction $transaction */
        $transaction = $this->resource;

        return [
            'refund_status' => $transaction->refund_status,
            'refund_method' => $transaction->refund_method,
            'refund_amount' => $transaction->refund_amount === null ? null : Money::fromDecimalString((string) $transaction->refund_amount)->minor(),
            'refund_reason' => $transaction->refund_reason,
            'refund_note' => $transaction->refund_note,
            'refunded_at' => $transaction->refunded_at,
        ];
    }

    /**
     * Rekening tujuan refund manual: hanya pembeli pemiliknya dan admin yang
     * mentransfer. Penjual melihat status refund, bukan rekeningnya.
     *
     * @return array{bank_name: ?string, account_number: string, account_name: ?string}|null
     */
    private function refundAccount(Request $request): ?array
    {
        /** @var Transaction $transaction */
        $transaction = $this->resource;
        $viewer = $request->user();

        if (! $transaction->refund_account_number || ! $viewer || $viewer->cannot('viewRefundAccount', $transaction)) {
            return null;
        }

        return [
            'bank_name' => $transaction->refund_bank_name,
            'account_number' => $transaction->refund_account_number,
            'account_name' => $transaction->refund_account_name,
        ];
    }
}
