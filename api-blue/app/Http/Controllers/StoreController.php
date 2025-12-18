<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\StoreStoreRequest;
use App\Http\Requests\StoreUpdateRequest;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\StoreResource;
use App\Interfaces\StoreRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Illuminate\Support\Str;

class StoreController extends Controller implements HasMiddleware
{
    private StoreRepositoryInterface $storeRepository;

    public function __construct(StoreRepositoryInterface $storeRepository) {
        $this->storeRepository = $storeRepository;
    }

    public static function middleware()
    {
        if (Auth::check()) {
            return [
                new Middleware(PermissionMiddleware::using(['store-create']), only: ['store']),
                new Middleware(PermissionMiddleware::using(['store-edit']), only: ['update']),
                new Middleware(PermissionMiddleware::using(['store-delete']), only: ['destroy']),
            ];
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $stores = $this->storeRepository->getAll($request->search,$request->is_verified,  $request->limit, $request->random, true);

            return ResponseHelper::jsonResponse(true, 'Data Toko Berhasil Diambil', StoreResource::collection($stores), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function getAllPaginated(Request $request)
    {
        $request = $request->validate([
            'search' => 'nullable|string',
            'is_verified' => 'nullable|boolean',
            'row_per_page' => 'required|integer'
        ]);

        try {
            $stores = $this->storeRepository->getAllPaginated($request['search'] ?? null, $request['is_verified'] ?? null, $request['row_per_page']);

            return ResponseHelper::jsonResponse(true, 'Data Toko Berhasil Diambil', PaginateResource::make($stores, StoreResource::class), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function getLocations()
    {
        try {
            $locations = $this->storeRepository->getLocations();
            return ResponseHelper::jsonResponse(true, 'Data Lokasi Berhasil Diambil', $locations, 200);
        } catch (\Exception $e) {
             return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStoreRequest $request)
    {
        $request = $request->validated();

        try {
            $store = $this->storeRepository->create($request);

            return ResponseHelper::jsonResponse(true, 'Data Toko Berhasil Ditambahkan', new StoreResource($store), 201);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $store = $this->storeRepository->getById($id);

            if (!$store) {
                return ResponseHelper::jsonResponse(true, 'Data Toko Tidak Ditemukan', null, 404);
            }

            return ResponseHelper::jsonResponse(true, 'Data Toko Berhasil Diambil', new StoreResource($store), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function showByUsername(string $username)
    {
        try {
            $store = $this->storeRepository->getByUsername($username);

            if (!$store) {
                return ResponseHelper::jsonResponse(true, 'Data Toko Tidak Ditemukan', null, 404);
            }

            return ResponseHelper::jsonResponse(true, 'Data Toko Berhasil Diambil', new StoreResource($store), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function showByUser()
    {
        try {
            $store = $this->storeRepository->getByUser();

            if (!$store) {
                return ResponseHelper::jsonResponse(true, 'Toko Belum Dibuat', null, 200);
            }

            return ResponseHelper::jsonResponse(true, 'Data Toko Berhasil Diambil', new StoreResource($store), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function updateVerifiedStatus(string $id)
    {
        try {
            $store = $this->storeRepository->getById($id);

            if (!$store) {
                return ResponseHelper::jsonResponse(true, 'Data Toko Tidak Ditemukan', null, 404);
            }

            $store = $this->storeRepository->updateVerifiedStatus($id, true);

            return ResponseHelper::jsonResponse(true, 'Data Toko Berhasil Diverifikasi', new StoreResource($store), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreUpdateRequest $request, string $id)
    {
        $request = $request->validated();

        try {
            $store = $this->storeRepository->getById($id);

            if (!$store) {
                return ResponseHelper::jsonResponse(true, 'Data Toko Tidak Ditemukan', null, 404);
            }

            $store = $this->storeRepository->update($id, $request);

            return ResponseHelper::jsonResponse(true, 'Data Toko Berhasil Diupdate', new StoreResource($store), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $store = $this->storeRepository->getById($id);

            if (!$store) {
                return ResponseHelper::jsonResponse(true, 'Data Toko Tidak Ditemukan', null, 404);
            }

            $store = $this->storeRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Data Toko Berhasil Dihapus', new StoreResource($store), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }
    public function registerStore(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:stores,name',
            'phone' => 'nullable|string',
            'city' => 'nullable|string',
            'address' => 'nullable|string',
            'postal_code' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();
            $user = Auth::user();

            if ($user->hasRole('store')) {
                return ResponseHelper::jsonResponse(false, 'Anda sudah memiliki toko.', null, 400);
            }

            // Create Store
            $store = $user->store()->create([
                'name' => $request->name,
                'username' => Str::slug($request->name) . '-' . Str::random(5),
                'phone' => $request->phone ?? $user->buyer?->phone_number,
                'city' => $request->city,
                'address' => $request->address,
                'postal_code' => $request->postal_code,
                'is_verified' => false,
                'logo' => 'default-store.png',
                'about' => '-',
                'address_id' => '-',
            ]);

            // Create Store Balance
            $store->storeBalance()->create([
                'balance' => 0
            ]);

            // Change Role: Remove 'buyer', Assign 'store'
            $user->removeRole('buyer');
            $user->assignRole('store');
            
            // Refresh permissions
            $user->permissions = $user->getPermissionsViaRoles()->pluck('name');
            $user->token = $user->createToken('auth_token')->plainTextToken; // Refresh token with new permissions

            DB::commit();

            return ResponseHelper::jsonResponse(true, 'Toko Berhasil Dibuat!', [
                'store' => new StoreResource($store),
                'user' => new \App\Http\Resources\UserResource($user), // Wrap in Resource
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }
}
