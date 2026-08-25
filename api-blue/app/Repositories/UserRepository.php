<?php

namespace App\Repositories;

use App\Interfaces\UserRepositoryInterface;
use App\Models\User;
use App\Services\AccountDeletionService;
use Exception;
use Illuminate\Support\Facades\DB;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(private AccountDeletionService $accountDeletionService) {}

    public function getAll(?string $search, ?int $limit, bool $execute, ?string $roles = null)
    {
        $query = User::with(['roles'])->where(function ($query) use ($search) {
            if ($search) {
                $query->search($search);
            }
        });

        if ($roles) {
            $query->whereHas('roles', function ($q) use ($roles) {
                $q->where('name', $roles);
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

    public function getCountByRole(string $role): int
    {
        return User::whereHas('roles', function ($q) use ($role) {
            $q->where('name', $role);
        })->count();
    }

    public function getAllPaginated(?string $search, ?int $rowPerPage, ?string $roles = null)
    {
        $query = $this->getAll($search, null, false, $roles);

        return $query->paginate($rowPerPage);
    }

    public function getById(string $id)
    {
        $query = User::where('id', $id);

        return $query->first();
    }

    public function create(array $data)
    {
        DB::beginTransaction();

        try {
            $user = new User;
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->password = bcrypt($data['password']);
            $user->save();

            DB::commit();

            return $user;
        } catch (Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage());
        }
    }

    public function update(string $id, array $data)
    {
        DB::beginTransaction();

        try {
            $user = User::find($id);

            $user->name = $data['name'];

            if (isset($data['password'])) {
                $user->password = bcrypt($data['password']);
            }

            $user->save();

            DB::commit();

            return $user;
        } catch (Exception $e) {
            DB::rollBack();

            throw new Exception($e->getMessage());
        }
    }

    public function delete(string $id)
    {
        try {
            $user = User::find($id);

            if (! $user) {
                throw new Exception('Data user tidak ditemukan.');
            }

            // Sama persis dengan jalur self-service (AuthRepository::deleteAccount)
            // -- revoke token, nonaktifkan toko, soft-delete. Dulu di sini cuma
            // $user->delete(): amannya (soft-delete) tetap terjadi begitu
            // SoftDeletes dipasang di model, tapi tokonya tidak pernah ikut
            // dinonaktifkan, jadi tetap tampil dan bisa dibeli.
            return $this->accountDeletionService->delete($user);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
