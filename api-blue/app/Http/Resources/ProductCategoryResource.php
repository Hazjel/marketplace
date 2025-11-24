<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCategoryResource extends JsonResource
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
            'parent_id' => $this->parent_id,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'name' => $this->name,
            'slug' => $this->slug,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'product_count' => $this->products->count() ?? 0,
            'children_count' => $this->childrens->count() ?? 0,
            'childrens' => ProductCategoryResource::collection($this->whenLoaded('childrens')),

        ];
    }
}
