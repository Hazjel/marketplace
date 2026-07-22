<?php

namespace App\Interfaces;

interface ProductRepositoryInterface
{
    public function getAll(?string $search, ?string $storeId, ?string $ProductCategoryId, ?int $limit, ?bool $random, bool $execute, array $filters = []);

    public function getAllPaginated(?string $search, ?string $storeId, ?string $ProductCategoryId, ?int $rowPerPage, array $filters = []);

    public function getTotalSold(?string $storeId = null);

    public function getProductCountForStore(string $storeId): int;

    public function getTotalCount(): int;

    public function getTopProducts(string $storeId, int $limit = 5);

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
