<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Interfaces\ProductViewRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductViewController extends Controller
{
    private ProductViewRepositoryInterface $productViewRepository;

    public function __construct(ProductViewRepositoryInterface $productViewRepository)
    {
        $this->productViewRepository = $productViewRepository;
    }

    public function store(Request $request, $productId)
    {
        // Endpoint ini di luar middleware auth:sanctum (guest juga boleh
        // tracking view), jadi wajib-tidaknya session_id ditentukan status
        // login SEKARANG (auth()->check()), bukan required_without field
        // "auth_user" yang tidak pernah dikirim siapapun -- rule lama itu
        // membuat session_id SELALU wajib walau user sudah login, sehingga
        // FE yang sengaja kirim null pas login selalu gagal validasi (422).
        $request->validate([
            'session_id' => [Rule::requiredIf(! auth()->check()), 'nullable', 'string'],
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
