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
        // Count products in this category + all child categories
        $productCount = $this->products->count();
        if ($this->childrens && $this->childrens->count() > 0) {
            foreach ($this->childrens as $child) {
                $productCount += $child->products->count();
            }
        }

        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'name' => $this->name,
            'slug' => $this->slug,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'product_count' => $productCount,
            'children_count' => $this->childrens->count() ?? 0,
            'childrens' => ProductCategoryResource::collection($this->whenLoaded('childrens')),
        ];
    }
}
