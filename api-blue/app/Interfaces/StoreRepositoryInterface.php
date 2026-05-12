<?php

namespace App\Interfaces;

interface StoreRepositoryInterface
{
    public function getAll(?string $search, ?bool $isVerified, ?int $limit, ?bool $random, bool $execute);
    public function getAllPaginated(?string $search, ?bool $isVerified, ?int $rowPerPage);
    public function getLocations();
    public function getById(string $id);
    public function getCategories(string $id);
    public function follow(string $storeId, string $userId);
    public function unfollow(string $storeId, string $userId);
    public function checkFollowStatus(string $storeId, string $userId);
    public function getReviews(string $storeId, ?int $limit = 10);
    public function getByUsername(string $username);
    public function getByUser();
    public function create(array $data);
    public function updateVerifiedStatus(string $id, bool $isVerified);
    public function update(string $id, array $data);
    public function delete(string $id);
}
