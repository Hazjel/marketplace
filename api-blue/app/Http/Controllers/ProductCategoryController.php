<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\ProductCategoryStoreRequest;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\ProductCategoryResource;
use App\Interfaces\ProductCategoryRepositoryInterface;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    private ProductCategoryRepositoryInterface $productCategoryRepository;

    public function __construct(ProductCategoryRepositoryInterface $productCategoryRepository) {
        $this->productCategoryRepository = $productCategoryRepository;

    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $productCategories = $this->productCategoryRepository->getAll($request->search, $request->is_parent, $request->limit, true);

            return ResponseHelper::jsonResponse(true, 'Data Kategori Produk Berhasil Diambil', ProductCategoryResource::collection($productCategories), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function getAllPaginated(Request $request)
    {
        $request = $request->validate([
            'search' => 'nullable|string',
            'is_parent' => 'nullable|boolean',
            'row_per_page' => 'required|integer'
        ]);

        try {
            $productCategories = $this->productCategoryRepository->getAllPaginated($request['search'] ?? null, $request['is_parent'] ?? null, $request['row_per_page']);

            return ResponseHelper::jsonResponse(true, 'Data Kategori Produk Berhasil Diambil', PaginateResource::make($productCategories, ProductCategoryResource::class), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductCategoryStoreRequest $request)
    {
        $request = $request->validated();

        try {
            $productCategory = $this->productCategoryRepository->create($request);

            return ResponseHelper::jsonResponse(true, 'Data Kategori Produk Berhasil Ditambahkan', new ProductCategoryResource($productCategory), 201);
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
            $productCategory = $this->productCategoryRepository->getById($id);

            if (!$productCategory) {
                return ResponseHelper::jsonResponse(true, 'Data Kategori Produk Tidak Ditemukan', null, 404);
            }

            return ResponseHelper::jsonResponse(true, 'Data Kategori Produk Berhasil Diambil', new ProductCategoryResource($productCategory), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function showBySlug(string $slug)
    {
        try {
            $productCategory = $this->productCategoryRepository->getBySlug($slug);

            if (!$productCategory) {
                return ResponseHelper::jsonResponse(true, 'Data Kategori Produk Tidak Ditemukan', null, 404);
            }

            return ResponseHelper::jsonResponse(true, 'Data Kategori Produk Berhasil Diambil', new ProductCategoryResource($productCategory), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
