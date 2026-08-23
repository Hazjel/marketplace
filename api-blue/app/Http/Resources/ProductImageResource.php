<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductImageResource extends JsonResource
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
            // Dulu ini mengembalikan ProductResource lengkap dari produk induk:
            // satu query per gambar, lalu kaskade store/user/kategori penuh di
            // atasnya, dan payload berlipat — padahal nama fieldnya cuma id.
            'product_id' => $this->product_id,
            'image' => str_starts_with($this->image, 'http') ? $this->image : asset('storage/'.$this->image),
            'is_thumbnail' => $this->is_thumbnail,
        ];
    }
}
