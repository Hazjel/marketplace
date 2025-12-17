<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductReviewResource extends JsonResource
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
            'product_id' => $this->product_id,
            'transaction' => $this->transaction ? new TransactionResource($this->transaction) : null,
            'user' => [
                'name' => $this->is_anonymous 
                    ? substr($this->user?->name ?? $this->transaction?->buyer?->user?->name ?? 'Unknown', 0, 1) . '***' . substr($this->user?->name ?? $this->transaction?->buyer?->user?->name ?? 'Unknown', -1) 
                    : ($this->user?->name ?? $this->transaction?->buyer?->user?->name ?? 'Unknown'),
                'avatar' => $this->is_anonymous 
                    ? 'default-avatar-url' 
                    : ($this->user?->profile_picture ?? $this->transaction?->buyer?->user?->profile_picture ?? null)
            ],
            'rating' => $this->rating,
            'review' => $this->review,
            'is_anonymous' => (bool)$this->is_anonymous,
            'attachments' => $this->attachments->map(function($attachment) {
                return [
                    'id' => $attachment->id,
                    'file_path' => asset($attachment->file_path),
                    'file_type' => $attachment->file_type
                ];
            }),
            'created_at' => $this->created_at
        ];
    }
}
