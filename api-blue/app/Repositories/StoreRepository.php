<?php

namespace App\Repositories;

use App\Interfaces\StoreRepositoryInterface;
use App\Models\ProductCategory;
use App\Models\ProductReview;
use App\Models\Store;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreRepository implements StoreRepositoryInterface
{
    public function getAll(?string $search, ?bool $isVerified, ?int $limit, ?bool $random, bool $execute, ?float $nearLat = null, ?float $nearLng = null)
    {
        $query = Store::where(function ($query) use ($search, $isVerified) {
            if ($search) {
                $query->search($search);
            }

            if ($isVerified !== null) {
                $query->where('is_verified', $isVerified);
            }
        })
            // Toko yang pemiliknya menghapus akun tidak ikut dihapus (lihat
            // migrasi is_active) supaya riwayat transaksi tetap utuh, tapi
            // harus berhenti tampil dan bisa dibeli. Endpoint ini dipakai
            // storefront publik dan store/all/paginated -- yang kedua cuma
            // dijaga auth:sanctum, bukan permission admin -- jadi tidak ada
            // "jalur admin" yang perlu dikecualikan dari filter ini.
            ->where('is_active', true)
            ->with(['user']);

        if ($nearLat !== null && $nearLng !== null) {
            // Urutkan berdasarkan jarak; toko tanpa koordinat tampil paling akhir
            $query->select('stores.*')
                ->selectRaw(
                    'ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) as distance_m',
                    [$nearLng, $nearLat]
                )
                ->orderByRaw('distance_m IS NULL, distance_m ASC');
        } elseif ($random) {
            $query->inRandomOrder();
        } else {
            $query->orderBy('created_at', 'desc');
        }

        if ($limit) {
            $query->take($limit);
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

    public function getLocations()
    {
        return Store::select('city')
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->distinct()
            ->pluck('city');
    }

    public function getCount(?bool $isVerified = null): int
    {
        $query = Store::query();

        if ($isVerified !== null) {
            $query->where('is_verified', $isVerified);
        }

        return $query->count();
    }

    public function getById(string $id)
    {
        $query = Store::where('id', $id)->with(['followers', 'user'])->withCount('followers');

        return $query->first();
    }

    public function getCategories(string $id)
    {
        return ProductCategory::whereHas('products', function ($query) use ($id) {
            $query->where('store_id', $id);
        })->get();
    }

    public function getByUsername(string $username)
    {
        $query = Store::where('username', $username)->with(['followers', 'user'])->withCount('followers');

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
            $store->username = Str::slug($data['name']).'-s'.rand(100000, 999999);
            $store->logo = $data['logo']->store('assets/store', 'public');
            $store->about = $data['about'];
            $store->phone = $data['phone'];
            $store->address_id = $data['address_id'];
            $store->city = $data['city'];
            $store->address = $data['address'];
            $store->postal_code = $data['postal_code'];
            $store->latitude = $data['latitude'] ?? null;
            $store->longitude = $data['longitude'] ?? null;

            $store->save();

            $store->storeBalance()->create([
                'balance' => 0,
            ]);

            DB::commit();

            return $store;
        } catch (Exception $e) {
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
        } catch (Exception $e) {
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
                $store->username = Str::slug($data['name']).'-s'.rand(100000, 999999);
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
            $store->latitude = $data['latitude'] ?? $store->latitude;
            $store->longitude = $data['longitude'] ?? $store->longitude;

            if (isset($data['ai_assistant_enabled'])) {
                $store->ai_assistant_enabled = $data['ai_assistant_enabled'];
            }

            $store->save();

            DB::commit();

            return $store;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function delete(string $id)
    {
        DB::beginTransaction();

        try {
            $store = Store::find($id);

            // Deaktivasi, BUKAN hard delete -- products.store_id,
            // store_balances.store_id, DAN transactions.store_id semuanya
            // onDelete('cascade'). Store model juga tidak SoftDeletes.
            // $store->delete() sebelumnya berarti menghapus toko juga
            // menghapus seluruh riwayat transaksi PEMBELI LAIN yang pernah
            // belanja di toko ini, plus saldo toko. Pola sama seperti
            // AccountDeletionService untuk User -- preserve data
            // finansial/transaksi, jangan cascade-delete.
            $store->is_active = false;
            $store->save();

            DB::commit();

            return $store;
        } catch (Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage());
        }
    }

    public function follow(string $storeId, string $userId)
    {
        $store = Store::find($storeId);
        if (! $store) {
            throw new Exception('Store not found');
        }

        $store->followers()->syncWithoutDetaching([$userId]);

        return true;
    }

    public function unfollow(string $storeId, string $userId)
    {
        $store = Store::find($storeId);
        if (! $store) {
            throw new Exception('Store not found');
        }

        $store->followers()->detach($userId);

        return true;
    }

    public function checkFollowStatus(string $storeId, string $userId)
    {
        $store = Store::find($storeId);
        if (! $store) {
            return false;
        }

        return $store->followers()->where('user_id', $userId)->exists();
    }

    public function getReviews(string $storeId, ?int $limit = 10)
    {
        return ProductReview::whereHas('product', function ($query) use ($storeId) {
            $query->where('store_id', $storeId);
        })
            ->with(['user', 'product', 'attachments'])
            ->orderBy('created_at', 'desc')
            ->paginate($limit);
    }
}
