<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_revenue' => (float) $this->resource['total_revenue'],
            'total_admin_fee' => (float) $this->resource['total_admin_fee'],
            'total_sellers' => (int) $this->resource['total_sellers'],
            'total_buyers' => (int) $this->resource['total_buyers'],
            'total_products' => (int) $this->resource['total_products'],
            'total_transactions' => (int) $this->resource['total_transactions'],
            'total_stores' => (int) $this->resource['total_stores'],
            'latest_stores' => StoreResource::collection($this->resource['latest_stores']),
            'latest_transactions' => TransactionResource::collection($this->resource['latest_transactions']),
        ];
    }
}
