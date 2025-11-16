<?php

namespace App\Repositories;

use App\Interfaces\ProductCategoryRepositoryInterface;
use App\Models\ProductCategory;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductCategoryRepository implements ProductCategoryRepositoryInterface
{
    public function getAll(?string $search, ?int $limit, bool $execute, ?bool $isParent = null)  // ✅ Pindahkan $isParent ke akhir
    {
        $query = ProductCategory::where(function ($query) use ($search, $isParent) {
            if ($search) {
                $query->search($search);
            }

            if ($isParent === true) {
                $query->whereNull('parent_id');
            }
        })
        ->with(['childrens' => function ($query) {
            $query->withCount('products', 'childrens');
        }])
        ->withCount('products', 'childrens')
        ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
        ->orderBy('name', 'asc');

        if ($limit) {
            $query->take($limit);
        }

        if ($execute) {
            return $query->get();
        }

        return $query;
    }

    public function getAllPaginated(?string $search, ?int $rowPerPage, ?bool $isParent = null)  // ✅ Pindahkan $isParent ke akhir
    {
        $query = $this->getAll($search, null, false, $isParent);  // ✅ Sesuaikan urutan parameter

        return $query->paginate($rowPerPage);
    }

    public function getById(string $id)
    {
        return ProductCategory::where('id', $id)
            ->with(['childrens' => function ($query) {
                $query->withCount('products', 'childrens');
            }])
            ->withCount('products', 'childrens')
            ->first();
    }

    public function getBySlug(string $slug)
    {
        return ProductCategory::where('slug', $slug)
            ->with(['childrens' => function ($query) {
                $query->withCount('products', 'childrens');
            }])
            ->withCount('products', 'childrens')
            ->first();
    }

    public function create(array $data)
    {
        DB::beginTransaction();

        try {
            $productCategory = new ProductCategory();

            if (isset($data['parent_id'])) {
                // Validasi parent exist
                $parent = ProductCategory::find($data['parent_id']);
                if (!$parent) {
                    throw new Exception('Kategori parent tidak ditemukan');
                }
                $productCategory->parent_id = $data['parent_id'];
            }

            if (isset($data['image'])) {
                $productCategory->image = $data['image']->store('assets/product-category', 'public');
            }

            $productCategory->name = $data['name'];
            $productCategory->slug = Str::slug($data['name']);

            if (isset($data['tagline'])) {
                $productCategory->tagline = $data['tagline'];
            }

            $productCategory->description = $data['description'];
            $productCategory->save();

            DB::commit();

            return $productCategory->load([
                'childrens' => function ($query) {
                    $query->withCount(['products as product_count', 'childrens as children_count']);
                }
            ])->loadCount(['products as product_count', 'childrens as children_count']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function update(string $id, array $data)
    {
        DB::beginTransaction();

        try {
            $productCategory = ProductCategory::find($id);

            if (!$productCategory) {
                throw new Exception('Kategori produk tidak ditemukan');
            }

            // Validasi tidak boleh set parent_id ke diri sendiri
            if (isset($data['parent_id']) && $data['parent_id'] === $id) {
                throw new Exception('Kategori tidak boleh menjadi parent dari dirinya sendiri');
            }

            if (isset($data['parent_id'])) {
                // Validasi parent exist
                $parent = ProductCategory::find($data['parent_id']);
                if (!$parent) {
                    throw new Exception('Kategori parent tidak ditemukan');
                }
                $productCategory->parent_id = $data['parent_id'];
            }

            if (isset($data['image'])) {
                // Hapus image lama jika ada
                if ($productCategory->image && Storage::disk('public')->exists($productCategory->image)) {
                    Storage::disk('public')->delete($productCategory->image);
                }

                $productCategory->image = $data['image']->store('assets/product-category', 'public');
            }

            $productCategory->name = $data['name'];
            $productCategory->slug = Str::slug($data['name']);

            if (isset($data['tagline'])) {
                $productCategory->tagline = $data['tagline'];
            }

            $productCategory->description = $data['description'];
            $productCategory->save();

            DB::commit();

            return $productCategory->load([
                'childrens' => function ($query) {
                    $query->withCount(['products as product_count', 'childrens as children_count']);
                }
            ])->loadCount(['products as product_count', 'childrens as children_count']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function delete(string $id)
    {
        DB::beginTransaction();

        try {
            $productCategory = ProductCategory::find($id);

            if (!$productCategory) {
                throw new Exception('Kategori produk tidak ditemukan');
            }

            if ($productCategory->image && Storage::disk('public')->exists($productCategory->image)) {
                Storage::disk('public')->delete($productCategory->image);
            }

            $productCategory->delete();

            DB::commit();

            return $productCategory;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }
}
