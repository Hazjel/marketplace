<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->roles->first()->name ?? '-';

        return [
            'id' => $this->id,
            'profile_picture' => $this->profile_picture,
            'name' => $this->name,
            'username' => $this->username, // Added
            'email' => $this->email,
            'role' => $role,
            'email_verified_at' => $this->email_verified_at,
            'permissions' => $this->permissions,
            'token' => $this->token,
            'store' => $this->store, // Return store if exists
            'buyer' => $this->buyer,  // Return buyer if exists (Even if role is store)
            'last_seen_at' => $this->last_seen_at, // Added
            'notification_prefs' => $this->notification_prefs ?? User::DEFAULT_NOTIFICATION_PREFS,
            'privacy_prefs' => $this->privacy_prefs ?? User::DEFAULT_PRIVACY_PREFS,
        ];
    }
}
