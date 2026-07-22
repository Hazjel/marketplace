<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
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
            'user' => new UserResource($this->user),
            'name' => $this->name,
            'username' => $this->username,
            'logo' => str_starts_with($this->logo ?? '', 'http') ? $this->logo : asset('storage/'.$this->logo),
            'about' => $this->about,
            'phone' => $this->phone,
            'address_id' => $this->address_id,
            'city' => $this->city,
            'address' => $this->address,
            'postal_code' => $this->postal_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'distance_m' => $this->when(isset($this->distance_m), fn () => round((float) $this->distance_m)),
            'is_verified' => $this->is_verified,
            'ai_assistant_enabled' => $this->ai_assistant_enabled,
            'product_count' => $this->products->count(),
            'transaction_count' => $this->transaction->count(),
            'created_at' => $this->created_at,
        ];
    }
}
