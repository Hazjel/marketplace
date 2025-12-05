<?php 

namespace  App\Interfaces;

use Illuminate\Support\Facades\Auth;
use App\Models\StoreBalance;
interface StoreBalanceRepositoryInterface 
{
    public function getAll(?string $search, ?int $limit, bool $execute);
    public function getAllPaginated(?string $search, ?int $rowPerPage);
    public function getById(string $id);

    public function getByStore();

    public function credit(
        string $id,
        string $amount
    );
    
    public function debit(
        string $id,
        string $amount
    );

    
}