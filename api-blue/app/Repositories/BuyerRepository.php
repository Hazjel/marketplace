<?php

namespace App\Repositories;

use App\Interfaces\BuyerRepositoryInterface;
use App\Models\Buyer;
use Exception;
use Illuminate\Support\Facades\DB;


class BuyerRepository implements BuyerRepositoryInterface
{
    public function getAll(?string $search, ?int $limit, bool $execute)
    {
        $query = Buyer::where(function ($query) use ($search) {
            if ($search) {
                $query->search($search);
            }
        });

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
        $query = Buyer::where('id', $id);

        return $query->first();
    }

    public function create(array $data)
    {
        DB::beginTransaction();

        try {
            $buyer = new Buyer;

            $buyer->user_id = $data['user_id'];
            $buyer->phone_number = $data['phone_number'];
            $buyer->save();

            DB::commit();

            return $buyer;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function update(string $id, array $data)
    {
        DB::beginTransaction();

        try {
            $buyer = Buyer::find($id);
   

            $buyer->phone_number = $data['phone_number'];
            $buyer->save();

            DB::commit();

            return $buyer;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function delete(string $id)
    {
        DB::beginTransaction();

        try {
            $buyer = Buyer::find($id);

            $buyer->delete();

            DB::commit();

            return $buyer;
        } catch (\Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage());
        }
    }
}
