<?php

namespace App\Interfaces;

interface ProductReviewRepositoryInterface
{
    public function create(array $data);

    public function getAllPaginated(array $params);

    public function getAverageRatingForStore(string $storeId): float;

    public function getReviewCountForStore(string $storeId): int;
}
