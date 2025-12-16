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

        try {
            // Prepare data
            $data = [
                'transaction_id' => $validated['transaction_id'],
                'product_id' => $validated['product_id'],
                'rating' => $validated['rating'],
                'review' => $validated['review'],
                'is_anonymous' => $request->boolean('is_anonymous', false)
            ];

            $productReview = $this->productReviewRepository->create($data);

            // Handle Attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $filename = time() . '_' . $file->getClientOriginalName();
                    
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

            return ResponseHelper::jsonResponse(true, 'Review berhasil ditambahkan', new ProductReviewResource($productReview), 201);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }
}
