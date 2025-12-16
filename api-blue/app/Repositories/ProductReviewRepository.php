<?php

namespace App\Repositories;

use App\Interfaces\ProductReviewRepositoryInterface;
use App\Models\ProductReview;
use Exception;
use Illuminate\Support\Facades\DB;

class ProductReviewRepository implements ProductReviewRepositoryInterface
{
    public function create(array $data)
    {
        DB::beginTransaction();

        try {
            $productReview = new ProductReview;

            $productReview->transaction_id = $data['transaction_id'];
            $productReview->product_id = $data['product_id'];
            $productReview->user_id = auth()->id();
            $productReview->rating = $data['rating'];
            $productReview->review = $data['review'];
            $productReview->save();

            DB::commit();

            return $productReview;
        } catch (\Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage());
        }
    }

    public function getAllPaginated(array $params)
    {
        $query = ProductReview::query()
            ->with(['product.productImages', 'transaction.buyer.user', 'user'])
            ->when(isset($params['store_id']), function ($q) use ($params) {
                $q->whereHas('product', function ($q) use ($params) {
                    $q->where('store_id', $params['store_id']);
                });
            })
            ->orderBy($params['sort_by'] ?? 'created_at', $params['sort_direction'] ?? 'desc');

        return $query->paginate($params['row_per_page'] ?? 10);
    }
}
