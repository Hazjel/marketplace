<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductCategory;
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
        // Jumlah produk kategori ini + seluruh anaknya. Pakai hasil withCount()
        // kalau tersedia; kalau tidak, COUNT(*) agregat — jangan pernah
        // menghidrasi baris produk hanya untuk dihitung.
        $productCount = $this->products_count ?? $this->products()->count();

        if ($this->relationLoaded('childrens')) {
            foreach ($this->childrens as $child) {
                $productCount += $child->products_count ?? $child->products()->count();
            }
            $childrenCount = $this->childrens->count();
        } else {
            $productCount += Product::whereIn(
                'product_category_id',
                ProductCategory::where('parent_id', $this->id)->select('id')
            )->count();
            $childrenCount = $this->childrens_count ?? $this->childrens()->count();
        }

        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'image' => $this->image ? asset('storage/'.$this->image) : null,
            'name' => $this->name,
            'slug' => $this->slug,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'product_count' => $productCount,
            'children_count' => $childrenCount,
            'childrens' => ProductCategoryResource::collection($this->whenLoaded('childrens')),
        ];
    }
}
