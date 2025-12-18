<?php

namespace App\Repositories;

use App\Models\Store;
use App\Interfaces\StoreBalanceRepositoryInterface;
use App\Models\StoreBalance;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StoreBalanceRepository implements StoreBalanceRepositoryInterface
{
    public function getAll(?string $search, ?int $limit, bool $execute)
    {
        $query = StoreBalance::where(function ($query) use ($search) {
            if ($search) {
                $query->search($search);
            }
        })->with(['storeBalanceHistories']);

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
        $query = StoreBalance::where('id', $id)->with(['storeBalanceHistories']);

        return $query->first();
    }

    public function getByStore()
    {
        $user = Auth::user();
        $query = StoreBalance::where('store_id', $user->store->id)->with(['storeBalanceHistories', 'store.user']);

        return $query->first();
    }


    public function credit(
        string $id,
        string $amount
    ) {
        DB::beginTransaction();

        try {
            $storeBalance = StoreBalance::find($id);
            $storeBalance->balance = $storeBalance->balance + $amount;
            $storeBalance->save();

            DB::commit();

            return $storeBalance;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function debit(
        string $id,
        string $amount
    ) {
        DB::beginTransaction();

        try {
            $storeBalance = StoreBalance::find($id);

            if($storeBalance->balance < $amount) {
                throw new Exception('Saldo Tidak Mencukupi');
            }

            $storeBalance->balance = $storeBalance->balance - $amount;
            $storeBalance->save();

            DB::commit();

            return $storeBalance;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }
}
