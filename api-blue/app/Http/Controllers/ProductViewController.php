<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Interfaces\ProductViewRepositoryInterface;
use Illuminate\Http\Request;

class ProductViewController extends Controller
{
    private ProductViewRepositoryInterface $productViewRepository;

    public function __construct(ProductViewRepositoryInterface $productViewRepository)
    {
        $this->productViewRepository = $productViewRepository;
    }

    public function store(Request $request, $productId)
    {
        $request->validate([
            'session_id' => 'required_without:auth_user|string|nullable',
        ]);

        try {
            $this->productViewRepository->record(
                auth()->id(),
                $request->input('session_id'),
                $productId,
            );

            return ResponseHelper::jsonResponse(true, 'Produk view tercatat', null, 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }
}
