<?php

namespace App\Repositories;

use App\Interfaces\StoreRepositoryInterface;
use App\Models\Store;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class StoreRepository implements StoreRepositoryInterface
{
    public function getAll(?string $search, ?bool $isVerified, ?int $limit, ?bool $random, bool $execute)
    {
        $query = Store::where(function ($query) use ($search, $isVerified) {
            if ($search) {
                $query->search($search);
            }

            if ($isVerified !== null) {
                $query->where('is_verified' ,$isVerified);
            }
        });

        $query->orderBy('created_at', 'desc');

        if ($limit) {
            $query->take($limit);
        }

        if ($random) {
            $query->inRandomOrder();
        }

        if ($execute) {
            return $query->get();
        }

        return $query;
    }

    public function getAllPaginated(?string $search, ?bool $isVerified, ?int $rowPerPage)
    {
        $query = $this->getAll($search, $isVerified, null, false, false);

        return $query->paginate($rowPerPage);
    }

    public function getById(string $id)
    {
        $query = Store::where('id', $id);

        return $query->first();
    }

    public function getByUsername(string $username)
    {
        $query = Store::where('username', $username);

        return $query->first();
    }

    public function getByUser()
    {
        $user = Auth::user();

        $query = Store::where('user_id', $user->id);

        return $query->first();
    }

    public function create(array $data)
    {
        DB::beginTransaction();

        try {
            $store = new Store;

            $store->user_id = $data['user_id'];
            $store->name = $data['name'];
            $store->username = Str::slug($data['name']) . '-s' . rand(100000, 999999);
            $store->logo = $data['logo']->store('assets/store', 'public');
            $store->about = $data['about'];
            $store->phone = $data['phone'];
            $store->address_id = $data['address_id'];
            $store->city = $data['city'];
            $store->address = $data['address'];
            $store->postal_code = $data['postal_code'];

            $store->save();

            $store->storeBalance()->create([
                'balance' => 0
            ]);

            DB::commit();

            return $store;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function updateVerifiedStatus(string $id, bool $isVerified)
    {
        DB::beginTransaction();

        try {
            $store = Store::find($id);

            $store->is_verified = $isVerified;
            $store->save();

            DB::commit();

            return $store;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function update(string $id, array $data)
    {
        DB::beginTransaction();

        try {
            $store = Store::find($id);

            $store->name = $data['name'];

            if ($data['name'] != $store->name) {
                $store->username = Str::slug($data['name']) . '-s' . rand(100000, 999999);
            }


            if (isset($data['logo'])) {
                $store->logo = $data['logo']->store('assets/store', 'public');
            }

            $store->about = $data['about'];
            $store->phone = $data['phone'];
            $store->address_id = $data['address_id'];
            $store->city = $data['city'];
            $store->address = $data['address'];
            $store->postal_code = $data['postal_code'];

            $store->save();

            DB::commit();

            return $store;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function delete(string $id)
    {
        DB::beginTransaction();

        try {
            $store = Store::find($id);
            $store->delete();

            DB::commit();

            return $store;
        } catch (\Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage());
        }
    }
}
