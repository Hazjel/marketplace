<?php

namespace App\Interfaces;

interface ProductReviewRepositoryInterface
{
    public function create(array $data);
    public function getAllPaginated(array $params);
}
