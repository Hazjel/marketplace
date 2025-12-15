<?php

namespace App\Http\Resources;

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
            'permissions' => $this->permissions,
            'token' => $this->token,
            'store' => $role === 'store' ? $this->store : null,
            'buyer' => $role === 'buyer' ? $this->buyer : null
        ];
    }
}
