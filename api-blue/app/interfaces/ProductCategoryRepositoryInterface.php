<?php

namespace App\Interfaces;

interface ProductCategoryRepositoryInterface
{
    public function getAll(?string $search, ?int $limit, bool $execute);
    public function getAllPaginated(?string $search, ?int $rowPerPage);
}
