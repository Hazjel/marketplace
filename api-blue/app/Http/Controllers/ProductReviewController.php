<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\ProductReviewStoreRequest;
use App\Http\Resources\ProductReviewResource;
use App\Interfaces\ProductReviewRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class ProductReviewController extends Controller implements HasMiddleware
{
    private ProductReviewRepositoryInterface $productReviewRepository;

    public function __construct(ProductReviewRepositoryInterface $productReviewRepository) {
        $this->productReviewRepository = $productReviewRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['product-review-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['product-review-list']), only: ['getAllPaginated']),
        ];
    }

    public function getAllPaginated(Request $request)
    {
        try {
            $productReviews = $this->productReviewRepository->getAllPaginated($request->all());

            return ResponseHelper::jsonResponse(true, 'Success', ProductReviewResource::collection($productReviews), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function store(ProductReviewStoreRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();

        try {
            // 1. Validasi Transaksi (Milik User & Status Completed)
            $transaction = \App\Models\Transaction::where('id', $validated['transaction_id'])
                ->whereHas('buyer', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->first();

            if (!$transaction) {
                return ResponseHelper::jsonResponse(false, 'Transaksi tidak ditemukan atau Anda tidak memiliki akses.', null, 404);
            }

            if ($transaction->delivery_status !== 'completed') {
                return ResponseHelper::jsonResponse(false, 'Anda hanya dapat mengulas transaksi yang telah selesai (Diterima).', null, 403);
            }

            // 2. Validasi Produk ada di Transaksi
            $productExists = $transaction->transactionDetails()
                ->where('product_id', $validated['product_id'])
                ->exists();

            if (!$productExists) {
                return ResponseHelper::jsonResponse(false, 'Produk ini tidak ada dalam transaksi tersebut.', null, 400);
            }

            // 3. Cek Duplicate Review
            $exists = \App\Models\ProductReview::where('transaction_id', $validated['transaction_id'])
                ->where('product_id', $validated['product_id'])
                ->exists();

            if ($exists) {
                return ResponseHelper::jsonResponse(false, 'Anda sudah memberikan ulasan untuk produk ini pada transaksi ini.', null, 409);
            }

            // Prepare data
            $data = [
                'transaction_id' => $validated['transaction_id'],
                'product_id' => $validated['product_id'],
                'user_id' => $user->id,
                'rating' => $validated['rating'],
                'review' => $validated['review'],
                'is_anonymous' => $request->boolean('is_anonymous', false)
            ];

            $productReview = $this->productReviewRepository->create($data);

            // Handle Attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                    
                    // Determine type based on mime
                    $mime = $file->getMimeType();
                    $type = str_starts_with($mime, 'video') ? 'video' : 'image';
                    
                    $file->move(public_path('upload/reviews'), $filename);
                    
                    \App\Models\ProductReviewAttachment::create([
                        'product_review_id' => $productReview->id,
                        'file_path' => 'upload/reviews/' . $filename,
                        'file_type' => $type
                    ]);
                }
            }

            // Load attachments for response
            $productReview->load('attachments');

            return ResponseHelper::jsonResponse(true, 'Ulasan berhasil dikirim!', new ProductReviewResource($productReview), 201);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }
}
