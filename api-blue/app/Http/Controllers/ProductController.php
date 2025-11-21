<?php

namespace App\Http\Controllers;

use App\Interfaces\ProductRepositoryInterface;
use App\Models\Product;
use App\Repositories\ProductRepository;
use Illuminate\Http\Request;
use App\Helpers\ResponseHelper;
use App\Http\Requests\ProductStoreRequest;
use App\Http\Requests\ProductUpdateRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\PaginateResource;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller implements HasMiddleware
{

    private ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public static function middleware()
    {
        if (auth::check()) {
            return [
                new Middleware(PermissionMiddleware::using(['product-list|product-create|product-edit|product-delete']), only: ['index', 'getAllPaginated', 'show', 'showBySlug']),
                new Middleware(PermissionMiddleware::using(['product-create']), only: ['store']),
                new Middleware(PermissionMiddleware::using(['product-edit']), only: ['update']),
                new Middleware(PermissionMiddleware::using(['product-delete']), only: ['destroy']),
            ];
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $products = $this->productRepository->getAll($request->search, $request->store_id, $request->product_category_id, $request->limit, $request->random, true);

            return ResponseHelper::jsonResponse(true, 'Data Produk Berhasil Diambil', ProductResource::collection($products), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function getAllPaginated(Request $request)
    {
        $request = $request->validate([
            'search' => 'nullable|string',
            'store_id' => 'nullable|exists:stores,id',
            'product_category_id' => 'nullable|exists:product_categories,id',
            'row_per_page' => 'required|integer'
        ]);

        try {
            $products = $this->productRepository->getAllPaginated($request['search'] ?? null, $request['store_id'] ?? null, $request['product_category_id'] ?? null, $request['row_per_page']);

            return ResponseHelper::jsonResponse(true, 'Data Produk Berhasil Diambil', PaginateResource::make($products, ProductResource::class), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductStoreRequest $request)
    {
        $request = $request->validated();

        try {
            $product = $this->productRepository->create($request);

            return ResponseHelper::jsonResponse(true, 'Produk Berhasil Ditambahkan', new ProductResource($product), 201);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $product = $this->productRepository->getById($id);

            if (!$product) {
                return ResponseHelper::jsonResponse(true, 'Data Produk Tidak Ditemukan', null, 404);
            }

            return ResponseHelper::jsonResponse(true, 'Data Produk Berhasil Diambil', new ProductResource($product), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

     public function showBySlug(string $slug)
    {
        try {
            $product = $this->productRepository->getBySlug($slug);

            if (!$product) {
                return ResponseHelper::jsonResponse(true, 'Data Produk Tidak Ditemukan', null, 404);
            }

            return ResponseHelper::jsonResponse(true, 'Data Produk Berhasil Diambil', new ProductResource($product), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProductUpdateRequest $request, string $id)
    {
        $request = $request->validated();

        try {
            $product = $this->productRepository->getById($id);

            if (!$product) {
                return ResponseHelper::jsonResponse(true, 'Data Produk Tidak Ditemukan', null, 404);
            }

            $product = $this->productRepository->update($id, $request);

            return ResponseHelper::jsonResponse(true, 'Data Produk Berhasil Diupdate', new ProductResource($product), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $product = $this->productRepository->getById($id);

            if (!$product) {
                return ResponseHelper::jsonResponse(true, 'Data Produk Tidak Ditemukan', null, 404);
            }

            $product = $this->productRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Data Produk Berhasil Dihapus', new ProductResource($product), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }
}
