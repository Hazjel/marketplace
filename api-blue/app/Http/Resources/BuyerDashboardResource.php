<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuyerDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_expense' => (float) $this->resource['total_expense'],
            'status_breakdown' => $this->resource['status_breakdown'],
            'chart' => $this->resource['chart'],
        ];
    }
}
