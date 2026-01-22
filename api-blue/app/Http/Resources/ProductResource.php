<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\StoreResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'=> $this->id,
            'store' => $this->store ? new StoreResource($this->store) : null,
            'product_category' => $this->productCategory ? new ProductCategoryResource($this->productCategory) : null,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'condition' => $this->condition,
            'price' => (float)(string)$this->price,
            'weight' => (float)(string)$this->weight,
            'stock' => $this->stock,
            'total_sold' => $this->total_sold,
            'created_at' => $this->created_at,
            'product_images' => ProductImageResource::collection($this->whenLoaded('productImages')),
            'thumbnail' => $this->whenLoaded('productImages', function () {
                $img = $this->productImages->where('is_thumbnail', 1)->first() ?? $this->productImages->first();
                return $img ? asset('storage/' . $img->image) : null;
            }),
            'product_reviews' => ProductReviewResource::collection($this->whenLoaded('productReviews')),
            'has_variants' => $this->has_variants,
            'variants' => $this->variants ? ProductVariantResource::collection($this->variants) : [],
        ];
    }
}
