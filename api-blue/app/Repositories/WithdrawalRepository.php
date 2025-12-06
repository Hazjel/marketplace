<?php

namespace App\Repositories;

use App\Interfaces\WithdrawalRepositoryInterface;
use App\Models\Withdrawal;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use App\Repositories\StoreBalanceRepository;
use App\Repositories\StoreBalanceHistoryRepository;
use Illuminate\Support\Facades\Auth;

class WithdrawalRepository implements WithdrawalRepositoryInterface
{
    public function getAll(?string $search, ?int $limit, bool $execute)
    {
        $query = Withdrawal::where(function ($query) use ($search) {
            if ($search) {
                $query->search($search);
            }
        });

        if (auth()->check() && auth()->user()->hasRole('store')) {
            $query->whereHas('storeBalance', function($query){
                $query->whereHas('store', function($query){
                    $query->where('id', auth()->user()->store->id ?? null);
                });
            });
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
        $query = Withdrawal::where('id', $id);

        return $query->first();
    }

    public function create(array $data) {
        DB::beginTransaction();

        try {
            $withdrawal = new Withdrawal;
            $withdrawal->store_balance_id = $data['store_balance_id'];
            $withdrawal->amount = $data['amount'];
            $withdrawal->bank_account_name = $data['bank_account_name'];
            $withdrawal->bank_account_number = $data['bank_account_number'];
            $withdrawal->bank_name = $data['bank_name'];

            $withdrawal->save();

            $storeBalanceRepository = new StoreBalanceRepository;
            $storeBalanceRepository->debit($withdrawal->store_balance_id, $withdrawal->amount,);

            $storeBalanceHistoryRepository = new StoreBalanceHistoryRepository();
            $storeBalanceHistoryRepository->create([
                'store_balance_id' => $withdrawal->store_balance_id,
                'type' => 'withdraw',
                'reference_id' => $withdrawal->id,
                'reference_type' => Withdrawal::class,
                'amount' => -$data['amount'],
                'remarks' => "Permintaan penarikan dana ke {$withdrawal->bank_name} - {$withdrawal->bank_account_number}"
            ]);

            DB::commit();

            return $withdrawal;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }
    public function approve(string $id, UploadedFile $proof)
    {
        DB::beginTransaction();

        try {
            $withdrawal = Withdrawal::find($id);

            $withdrawal->status = 'approved';
            $withdrawal->proof = $proof->store('assets/withdrawal', 'public');

            $withdrawal->save();

            $storeBalanceHistoryRepository = new StoreBalanceHistoryRepository();
            $storeBalanceHistoryRepository->create([
                'store_balance_id' => $withdrawal->store_balance_id,
                'type' => 'withdraw',
                'reference_id' => $withdrawal->id,
                'reference_type' => Withdrawal::class,
                'amount' => -$withdrawal->amount,
                'remarks' => "Permintaan penarikan dana ke {$withdrawal->bank_name} - {$withdrawal->bank_account_number} disetujui"
            ]);

            DB::commit();

            return $withdrawal;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }
}
