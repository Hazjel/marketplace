<?php

namespace App\Repositories;

use App\Interfaces\ProductRepositoryInterface;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Interfaces\ProductImageRepositoryInterface;
use App\Repositories\ProductImageRepository;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProductRepository implements ProductRepositoryInterface
{
    public function getAll(?string $search, ?string $storeId, ?string $ProductCategoryId, ?int $limit, ?bool $random, bool $execute, array $filters = [])
    {
        $query = Product::where(function ($query) use ($search, $storeId, $ProductCategoryId, $filters) {
            if ($search) {
                $query->search($search);
            }

            if ($storeId) {
                $query->where('store_id', $storeId);
            }

            if ($ProductCategoryId !== null) {
                $query->where('product_category_id', $ProductCategoryId);
            }

            // Filters
            if (!empty($filters['min_price'])) {
                $query->where('price', '>=', $filters['min_price']);
            }
            if (!empty($filters['max_price'])) {
                $query->where('price', '<=', $filters['max_price']);
            }
            if (!empty($filters['condition'])) {
                // Ensure array
                $conditions = is_array($filters['condition']) ? $filters['condition'] : [$filters['condition']];
                $query->whereIn('condition', $conditions);
            }
            if (!empty($filters['city'])) {
                $city = $filters['city'];
                $query->whereHas('store', function($q) use ($city) {
                    if (is_array($city)) {
                        $q->whereIn('city', array_filter($city)); // array_filter to remove nulls
                    } else {
                        $q->where('city', $city);
                    }
                });
            }
            if (!empty($filters['min_rating'])) {
                $minRating = $filters['min_rating'];
                // Check if has review >= minRating
                $query->whereHas('productReviews', function($q) use ($minRating) {
                     $q->where('rating', '>=', $minRating);
                });
            }

            // New Filters
            if (!empty($filters['stock_status'])) {
                 if ($filters['stock_status'] == 'ready_stock') {
                     $query->where('stock', '>', 0);
                 }
            }

            if (!empty($filters['created_since'])) { // days
                 $days = (int) $filters['created_since'];
                 $query->where('created_at', '>=', now()->subDays($days));
            }
        })->with('productImages');

        // Removed implicit filtering by store_id for store role users to allow them to see all products in buyer mode


        if ($limit) {
            $query->take($limit);
        }

        if ($random) {
            $query->inRandomOrder();
        } elseif (!empty($filters['sort_by'])) {
            $sortDirection = $filters['sort_direction'] ?? 'desc';
            
            if ($filters['sort_by'] === 'price') {
                $query->orderBy('price', $sortDirection);
            } elseif ($filters['sort_by'] === 'created_at') {
                $query->orderBy('created_at', $sortDirection);
            } elseif ($filters['sort_by'] === 'sold') {
                $query->withSum(['transactionDetails' => function ($q) {
                    $q->whereHas('transaction', function ($t) {
                        $t->where('payment_status', 'paid');
                    });
                }], 'qty')->orderBy('transaction_details_sum_qty', $sortDirection);
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        if ($execute) {
            return $query->get();
        }

        return $query;
    }

    public function getAllPaginated(?string $search, ?string $storeId, ?string $ProductCategoryId = null, ?int $rowPerPage, array $filters = [])
    {
        $query = $this->getAll($search, $storeId, $ProductCategoryId, null, false, false, $filters);

        return $query->paginate($rowPerPage);
    }

    public function getTotalSold()
    {
        // Calculate Total Sold based on PRODUCTS belonging to the store
        // This ensures consistency with the "Product List" which shows products of the store.
        // We join transaction_details -> transactions to check Payment Status.
        // We filter by products.store_id.

        $query = DB::table('transaction_details')
            ->join('products', 'transaction_details.product_id', '=', 'products.id')
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->where('transactions.payment_status', 'paid');

        if (auth()->check() && auth()->user()->hasRole('store')) {
            $query->where('products.store_id', auth()->user()->store->id ?? null);
        }

        return $query->sum('transaction_details.qty');
    }

    public function getById(string $id)
    {
        $query = Product::where('id', $id)->with(['productImages', 'productReviews.user', 'productReviews.attachments']);

        return $query->first();
    }

    public function getBySlug(string $slug)
    {
        $query = Product::where('slug', $slug)->with(['productImages', 'productReviews.user', 'productReviews.attachments']);

        return $query->first();
    }
    public function create(array $data)
    {
        DB::beginTransaction();

        try {
            $product = new Product();
            $product->store_id = $data['store_id'];
            $product->product_category_id = $data['product_category_id'];
            $product->name = $data['name'];
            $product->slug = Str::slug($data['name']) . '-i' . rand(100000, 999999) . '.' . rand(10000000, 9999999);
            $product->description = $data['description'];
            $product->condition = $data['condition'];
            $product->price = $data['price'];
            $product->weight = $data['weight'];
            $product->stock = $data['stock'];
            $product->save();

            $productImageRepository = new ProductImageRepository;
            if (isset($data['product_images'])) {
                foreach ($data['product_images'] as $productImage) {
                    $productImageRepository->create([
                        'product_id' => $product->id,
                        'image' => $productImage['image'],
                        'is_thumbnail' => $productImage['is_thumbnail'],
                    ]);
                }
            }

            DB::commit();

            return $product;


        } catch (\Throwable $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function update(string $id, array $data)
    {
        DB::beginTransaction();

        try {
            $product = Product::find($id);
            $product->store_id = $data['store_id'];
            $product->product_category_id = $data['product_category_id'];
            $product->name = $data['name'];
            $product->slug = Str::slug($data['name']) . '-i' . rand(100000, 999999) . '.' . rand(10000000, 9999999);
            $product->description = $data['description'];
            $product->condition = $data['condition'];
            $product->price = $data['price'];
            $product->weight = $data['weight'];
            $product->stock = $data['stock'];
            $product->save();

            $productImageRepository = new ProductImageRepository;

            if (isset($data['deleted_product_images'])) {
                foreach ($data['deleted_product_images'] as $productImage) {
                    $productImageRepository->delete($productImage);
                }
            }

            if (isset($data['product_images'])) {
                foreach ($data['product_images'] as $productImage) {
                    if (!isset($productImage['id'])) {
                        $productImageRepository->create([
                            'product_id' => $product->id,
                            'image' => $productImage['image'],
                            'is_thumbnail' => $productImage['is_thumbnail'],
                        ]);
                    }
                }
            }

            DB::commit();

            return $product;


        } catch (\Throwable $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function delete(string $id)
    {
        DB::beginTransaction();

        try {
            $product = Product::find($id);
            $product->delete();

            DB::commit();

            return $product;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }
}
