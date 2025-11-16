<?php

Namespace App\Interfaces;
interface TransactionRepositoryInterface 
{
    public function getAll(?string $search, ?int $limit, bool $execute);
    public function getAllPaginated(?string $search, ?int $rowPerPage);
    public function getById(string $id);
    public function getByCode(string $code);
}