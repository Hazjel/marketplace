<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\ProductResource;
use App\Interfaces\WishlistRepositoryInterface;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    private WishlistRepositoryInterface $wishlistRepository;

    public function __construct(WishlistRepositoryInterface $wishlistRepository)
    {
        $this->wishlistRepository = $wishlistRepository;
    }

    public function index()
    {
        try {
            $wishlists = $this->wishlistRepository->getByUserId(auth()->id());
            // Extract products from wishlist items
            $products = $wishlists->pluck('product');
            return ResponseHelper::jsonResponse(true, 'Data Wishlist Berhasil Diambil', ProductResource::collection($products), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        try {
            $status = $this->wishlistRepository->toggle(auth()->id(), $request->product_id);
            $message = $status === 'added' ? 'Produk berhasil ditambahkan ke wishlist' : 'Produk berhasil dihapus dari wishlist';
            return ResponseHelper::jsonResponse(true, $message, ['status' => $status], 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }
}
