<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $price = Money::fromDecimalString((string) $this->price)->minor();
        $compareAt = $this->resource->compare_at_price === null ? null : Money::fromDecimalString((string) $this->resource->compare_at_price)->minor();
        $discount = Product::discountPercent($price, $compareAt);

        return [
            'id' => $this->id,
            'store' => $this->store ? new StoreResource($this->store) : null,
            'product_category' => $this->productCategory ? new ProductCategoryResource($this->productCategory) : null,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'condition' => $this->condition,
            // price is whole rupiah (Sprint B3.2a); a fractional legacy
            // value throws rather than silently truncating. weight is kg,
            // not money.
            'price' => $price,
            // Display only, never charged. For variant products this is the
            // cheapest variant's (ProductRepository keeps it in sync).
            'compare_at_price' => $discount === null ? null : $compareAt,
            'discount_percent' => $discount,
            'weight' => (float) (string) $this->weight,
            'stock' => $this->stock,
            'total_sold' => $this->total_sold,
            // Only where the query used withRecentSold(); absent elsewhere
            // (cart, transactions) rather than a wrong false.
            'is_best_seller' => $this->when(
                array_key_exists('recent_sold_qty', $this->resource->getAttributes()),
                fn () => (int) $this->resource->recent_sold_qty >= Product::BEST_SELLER_MIN_QTY,
            ),
            'created_at' => $this->created_at,
            'product_images' => ProductImageResource::collection($this->whenLoaded('productImages')),
            'thumbnail' => $this->whenLoaded('productImages', function () {
                $img = $this->productImages->where('is_thumbnail', 1)->first() ?? $this->productImages->first();
                if (! $img) {
                    return null;
                }
                // Support both full URLs (placeholder) and local storage paths
                if (str_starts_with($img->image, 'http')) {
                    return $img->image;
                }

                return asset('storage/'.$img->image);
            }),
            'product_reviews' => ProductReviewResource::collection($this->whenLoaded('productReviews')),
            'has_variants' => $this->has_variants,
            'variants' => $this->getVariantsSafely(),
        ];
    }

    /**
     * Varian tersimpan di MongoDB. Hanya query kalau produk memang punya
     * varian, dan jangan sampai kegagalan Mongo mematikan serialisasi produk.
     */
    private function getVariantsSafely(): array|AnonymousResourceCollection
    {
        if (! $this->has_variants) {
            return [];
        }

        try {
            return $this->variants ? ProductVariantResource::collection($this->variants) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
