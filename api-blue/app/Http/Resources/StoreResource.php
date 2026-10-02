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
            'user' => $this->owner($request),
            'name' => $this->name,
            'username' => $this->username,
            'logo' => empty($this->logo) ? null : (str_starts_with($this->logo, 'http') ? $this->logo : asset('storage/'.$this->logo)),
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
            // Pakai hasil withCount() kalau query pemanggil sudah menyediakannya.
            // Fallback ke COUNT(*) — tetap satu query, tapi tidak menghidrasi
            // seluruh baris relasi hanya untuk diambil jumlahnya.
            'product_count' => $this->products_count ?? $this->products()->count(),
            'transaction_count' => $this->transaction_count ?? $this->transaction()->count(),
            'created_at' => $this->created_at,
        ];
    }

    // Public store listings returned the owner's full UserResource: email,
    // buyer profile (phone) and more, for every seller. Only the owner and
    // admins get that; everyone else gets what a storefront shows.
    private function owner(Request $request): mixed
    {
        $owner = $this->user;
        if (! $owner) {
            return null;
        }

        $viewer = $request->user();
        if ($viewer && ($viewer->id === $owner->id || $viewer->hasRole('admin'))) {
            return new UserResource($owner);
        }

        return [
            'id' => $owner->id,
            'name' => $owner->name,
            'username' => $owner->username,
            'profile_picture' => $owner->profile_picture,
            'last_seen_at' => $owner->last_seen_at,
        ];
    }
}
