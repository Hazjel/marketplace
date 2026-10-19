<?php

namespace App\Repositories;

use App\Interfaces\StoreBalanceHistoryRepositoryInterface;
use App\Models\StoreBalance;
use App\Models\StoreBalanceHistory;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StoreBalanceHistoryRepository implements StoreBalanceHistoryRepositoryInterface
{
    public function getAll(?string $search, ?int $limit, bool $execute)
    {
        $query = StoreBalanceHistory::where(function ($query) use ($search) {
            if ($search) {
                $query->search($search);
            }
        });

        if (! Auth::user()?->hasRole('admin')) {
            $query->whereIn('store_balance_id', StoreBalance::query()
                ->join('stores', 'stores.id', '=', 'store_balances.store_id')
                ->where('stores.user_id', Auth::id())
                ->select('store_balances.id'));
        }

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
        $query = $this->getAll(null, null, false)->where('id', $id);

        return $query->first();
    }

    public function create(array $data)
    {
        DB::beginTransaction();

        try {
            $storeBalanceHistory = new StoreBalanceHistory;

            $storeBalanceHistory->store_balance_id = $data['store_balance_id'];
            $storeBalanceHistory->type = $data['type'];
            $storeBalanceHistory->reference_id = $data['reference_id'];
            $storeBalanceHistory->reference_type = $data['reference_type'];
            $storeBalanceHistory->amount = $data['amount'];
            $storeBalanceHistory->remarks = $data['remarks'];
            $storeBalanceHistory->save();

            DB::commit();

            return $storeBalanceHistory;
        } catch (Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage());
        }
    }

    public function update(string $id, array $data)
    {
        DB::beginTransaction();

        try {
            $storeBalanceHistory = StoreBalanceHistory::find($id);

            $storeBalanceHistory->type = $data['type'];
            $storeBalanceHistory->reference_id = $data['reference_id'];
            $storeBalanceHistory->reference_type = $data['reference_type'];
            $storeBalanceHistory->amount = $data['amount'];
            $storeBalanceHistory->remarks = $data['remarks'];
            $storeBalanceHistory->save();

            DB::commit();

            return $storeBalanceHistory;
        } catch (Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage());
        }
    }
}
