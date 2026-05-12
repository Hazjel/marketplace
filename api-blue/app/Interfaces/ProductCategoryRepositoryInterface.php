<?php

namespace App\Interfaces;

interface ProductCategoryRepositoryInterface
{
    public function getAll(?string $search, ?int $limit, bool $execute, ?bool $isParent = null);
    public function getAllPaginated(?string $search, ?int $rowPerPage, ?bool $isParent = null);  
    public function getById(string $id);
    public function getBySlug(string $slug);
    public function create(array $data);
    public function update(string $id, array $data);
    public function delete(string $id);
}
