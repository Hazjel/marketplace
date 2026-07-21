<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'balance' => (float) ($this->resource['balance'] ?? 0),
            'pending_balance' => (float) ($this->resource['pending_balance'] ?? 0),
            'total_orders' => (int) $this->resource['total_orders'],
            'status_breakdown' => $this->resource['status_breakdown'],
            'total_reviews' => (int) $this->resource['total_reviews'],
            'average_rating' => round((float) $this->resource['average_rating'], 1),
            'total_products' => (int) $this->resource['total_products'],
            'top_products' => ProductResource::collection($this->resource['top_products']),
            'chart' => $this->resource['chart'],
            'trend' => $this->resource['trend'],
        ];
    }
}
