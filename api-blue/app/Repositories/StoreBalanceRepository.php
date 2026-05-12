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
            // Pessimistic lock prevents double-credit from concurrent webhooks
            $storeBalance = StoreBalance::where('id', $id)->lockForUpdate()->first();
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
            // Pessimistic lock prevents concurrent over-debit (withdrawal race)
            $storeBalance = StoreBalance::where('id', $id)->lockForUpdate()->first();

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

    /**
     * Credit ke pending_balance (escrow).
     * Dana ditahan sampai pesanan selesai.
     */
    public function creditPending(string $id, float $amount): StoreBalance
    {
        DB::beginTransaction();

        try {
            $storeBalance = StoreBalance::where('id', $id)->lockForUpdate()->first();

            if (!$storeBalance) {
                throw new Exception('Store Balance not found');
            }

            $storeBalance->pending_balance = $storeBalance->pending_balance + $amount;
            $storeBalance->save();

            DB::commit();

            return $storeBalance;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Release pending_balance ke available balance.
     * Dipanggil saat buyer konfirmasi terima / auto-complete.
     */
    public function releasePending(string $id, float $amount): StoreBalance
    {
        DB::beginTransaction();

        try {
            $storeBalance = StoreBalance::where('id', $id)->lockForUpdate()->first();

            if (!$storeBalance) {
                throw new Exception('Store Balance not found');
            }

            if ($storeBalance->pending_balance < $amount) {
                throw new Exception('Pending balance tidak mencukupi untuk dirilis');
            }

            // Kurangi pending, tambahkan ke available
            $storeBalance->pending_balance = $storeBalance->pending_balance - $amount;
            $storeBalance->balance = $storeBalance->balance + $amount;
            $storeBalance->save();

            DB::commit();

            return $storeBalance;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Refund pending_balance (batalkan escrow).
     * Dipanggil saat transaksi dibatalkan/gagal setelah payment confirmed.
     */
    public function refundPending(string $id, float $amount): StoreBalance
    {
        DB::beginTransaction();

        try {
            $storeBalance = StoreBalance::where('id', $id)->lockForUpdate()->first();

            if (!$storeBalance) {
                throw new Exception('Store Balance not found');
            }

            if ($storeBalance->pending_balance < $amount) {
                // Jika pending_balance sudah kurang (edge case), set ke 0
                $storeBalance->pending_balance = 0;
            } else {
                $storeBalance->pending_balance = $storeBalance->pending_balance - $amount;
            }

            $storeBalance->save();

            DB::commit();

            return $storeBalance;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }
}
