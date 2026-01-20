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
            $filters = $request->only(['min_price', 'max_price', 'condition', 'city', 'min_rating']);
            $products = $this->productRepository->getAll($request->search, $request->store_id, $request->product_category_id, $request->limit, $request->random, true, $filters);

            return ResponseHelper::jsonResponse(true, 'Data Produk Berhasil Diambil', ProductResource::collection($products), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function getAllPaginated(Request $request)   
    {
        $validated = $request->validate([
            'search' => 'nullable|string',
            'store_id' => 'nullable|exists:stores,id',
            'product_category_id' => 'nullable|exists:product_categories,id',
            'row_per_page' => 'required|integer',
            'min_price' => 'nullable|numeric',
            'max_price' => 'nullable|numeric',
            'condition' => 'nullable',
            'city' => 'nullable|string',
            'min_rating' => 'nullable|numeric|min:0|max:5',
            'stock_status' => 'nullable|string', 
            'created_since' => 'nullable|integer', 
            'sort_by' => 'nullable|string|in:price,created_at,sold',
            'sort_direction' => 'nullable|string|in:asc,desc',
        ]);

        try {
            $filters = $request->only(['min_price', 'max_price', 'condition', 'city', 'min_rating', 'stock_status', 'created_since', 'sort_by', 'sort_direction']);
            $products = $this->productRepository->getAllPaginated($validated['search'] ?? null, $validated['store_id'] ?? null, $validated['product_category_id'] ?? null, $validated['row_per_page'], $filters);
            $totalSold = $this->productRepository->getTotalSold();
            // Log::info("CONTROLLER debug totalSold: " . $totalSold);
            // Log::info("CONTROLLER debug Auth: " . (auth()->check() ? auth()->user()->id : 'Guest'));

            $resource = (new PaginateResource($products, ProductResource::class));
            
            // Resolve resource to array to ensure we can modify structure reliably
            $data = $resource->resolve(request());
            
            // Append total_sold to meta
            if (isset($data['meta'])) {
                $data['meta']['total_sold'] = (int) $totalSold;
            } else {
                 $data['meta'] = ['total_sold' => (int) $totalSold];
            }

            return ResponseHelper::jsonResponse(true, 'Data Produk Berhasil Diambil', $data, 200);
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
