<?php

namespace App\Interfaces;

interface ProductRepositoryInterface{
    public function getAll(?string $search, ?string $storeId, ?string $ProductCategoryId, ?int $limit,?bool $random, bool $execute);
    public function getAllPaginated(?string $search, ?string $storeId, ?string $ProductCategoryId = null, ?int $rowPerPage);
    public function getById(
        string $id
    );
    public function getBySlug(
        string $slug
    );

    public function create(
        array $data
    );

    public function update(
        string $id,
        array $data
    );

    public function delete(
        string $id
    );

}
