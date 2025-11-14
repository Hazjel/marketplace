<?php

namespace App\Repositories;

use App\Interfaces\ProductCategoryRepositoryInterface;
use App\Models\ProductCategory;

class ProductCategoryRepository implements ProductCategoryRepositoryInterface
{
    public function getAll(?string $search, ?int $limit, bool $execute)
    {
        $query = ProductCategory::where(function ($query) use ($search) {
            if ($search) {
                $query->search($search);
            }
        });

        if ($limit) {
            $query->take($limit);
        }

        if ($execute) {
            return $query->get();
        }

        return $query;
    }

    public function getAllPaginated(?string $search, ?int $rowPerPage)
    {
        $query = $this->getAll($search, null, false);

        return $query->paginate($rowPerPage);
    }

    public function getById(string $id)
    {
        $query = ProductCategory::where('id', $id);

        return $query->first();
    }
}
