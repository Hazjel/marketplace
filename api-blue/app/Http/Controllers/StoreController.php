<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\StoreStoreRequest;
use App\Http\Requests\StoreUpdateRequest;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\ProductCategoryResource;
use App\Http\Resources\ProductReviewResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UserResource;
use App\Interfaces\StoreRepositoryInterface;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Middleware\PermissionMiddleware;

class StoreController extends Controller implements HasMiddleware
{
    private StoreRepositoryInterface $storeRepository;

    public function __construct(StoreRepositoryInterface $storeRepository)
    {
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
            $request->validate([
                'lat' => 'nullable|numeric|between:-90,90|required_with:lng',
                'lng' => 'nullable|numeric|between:-180,180|required_with:lat',
            ]);

            $search = $request->search;
            $is_verified = $request->is_verified;
            $limit = $request->limit;
            $random = $request->random;
            $nearLat = $request->lat !== null ? (float) $request->lat : null;
            $nearLng = $request->lng !== null ? (float) $request->lng : null;

            // Don't cache random/nearby requests — each call must be unique
            if ($random || $nearLat !== null) {
                $stores = $this->storeRepository->getAll($search, $is_verified, $limit, $random, true, $nearLat, $nearLng);
            } else {
                $cacheKey = "stores_index_search_{$search}_verified_{$is_verified}_limit_{$limit}";
                $stores = Cache::tags(['stores'])->remember($cacheKey, 600, function () use ($search, $is_verified, $limit, $random) {
                    return $this->storeRepository->getAll($search, $is_verified, $limit, $random, true);
                });
            }

            return ResponseHelper::jsonResponse(true, 'Data Toko Berhasil Diambil', StoreResource::collection($stores), 200);
        } catch (\Exception $e) {
            return ResponseHelper::exceptionResponse($e);
        }
    }

    public function getAllPaginated(Request $request)
    {
        $request = $request->validate([
            'search' => 'nullable|string',
            'is_verified' => 'nullable|boolean',
            'row_per_page' => 'required|integer|min:1|max:100',
        ]);

        try {
            $stores = $this->storeRepository->getAllPaginated($request['search'] ?? null, $request['is_verified'] ?? null, $request['row_per_page']);

            return ResponseHelper::jsonResponse(true, 'Data Toko Berhasil Diambil', PaginateResource::make($stores, StoreResource::class), 200);
        } catch (\Exception $e) {
            return ResponseHelper::exceptionResponse($e);
        }
    }

    public function getLocations()
    {
        try {
            $locations = $this->storeRepository->getLocations();

            return ResponseHelper::jsonResponse(true, 'Data Lokasi Berhasil Diambil', $locations, 200);
        } catch (\Exception $e) {
            return ResponseHelper::exceptionResponse($e);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStoreRequest $request)
    {
        $request = $request->validated();

        // KOREKSI dari fix sebelumnya: memaksa user_id ke pemanggil tetap
        // membiarkan non-admin membuat toko KEDUA. Domain model ini
        // single-store per user (User::store() adalah hasOne, tanpa unique
        // constraint di DB), dan onboarding resmi lewat register-store()
        // sudah menolak user yang hasRole('store'). Store kedua diam-diam
        // merusak asumsi single-store di tempat lain: SellerVoucherController
        // pakai Auth::user()->store?->id (ambigu kalau ada >1),
        // AccountDeletionService cuma menonaktifkan $user->store (singular,
        // toko lain tetap aktif), dan seller yang toko-nya dinonaktifkan
        // admin masih bisa bikin toko baru lewat endpoint ini karena
        // permission 'store-create' tidak dicabut bareng is_active.
        //
        // Endpoint ini sekarang admin-only. register-store() tetap
        // satu-satunya jalur onboarding untuk seller.
        if (! auth()->user()->hasRole('admin')) {
            return ResponseHelper::jsonResponse(false, 'Unauthorized', null, 403);
        }

        // Admin pun tidak boleh membuat toko kedua untuk user yang sudah
        // punya satu -- domain model ini memang single-store per user.
        if (Store::where('user_id', $request['user_id'])->exists()) {
            return ResponseHelper::jsonResponse(false, 'User ini sudah memiliki toko', null, 409);
        }

        try {
            $store = $this->storeRepository->create($request);
            Cache::tags(['stores'])->flush();

            return ResponseHelper::jsonResponse(true, 'Data Toko Berhasil Ditambahkan', new StoreResource($store), 201);
        } catch (\Exception $e) {
            return ResponseHelper::exceptionResponse($e);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $store = $this->storeRepository->getById($id);

            // Rute publik, tidak diautentikasi -- toko yang pemiliknya
            // menghapus akun tetap ada di database (lihat is_active), tapi
            // tidak boleh terlihat atau bisa dibeli di sini.
            if (! $store || ! $store->is_active) {
                return ResponseHelper::jsonResponse(true, 'Data Toko Tidak Ditemukan', null, 404);
            }

            return ResponseHelper::jsonResponse(true, 'Data Toko Berhasil Diambil', new StoreResource($store), 200);
        } catch (\Exception $e) {
            return ResponseHelper::exceptionResponse($e);
        }
    }

    public function showByUsername(string $username)
    {
        try {
            $store = $this->storeRepository->getByUsername($username);

            if (! $store || ! $store->is_active) {
                return ResponseHelper::jsonResponse(true, 'Data Toko Tidak Ditemukan', null, 404);
            }

            return ResponseHelper::jsonResponse(true, 'Data Toko Berhasil Diambil', new StoreResource($store), 200);
        } catch (\Exception $e) {
            return ResponseHelper::exceptionResponse($e);
        }
    }

    public function showByUser()
    {
        try {
            $store = $this->storeRepository->getByUser();

            if (! $store) {
                return ResponseHelper::jsonResponse(true, 'Toko Belum Dibuat', null, 200);
            }

            return ResponseHelper::jsonResponse(true, 'Data Toko Berhasil Diambil', new StoreResource($store), 200);
        } catch (\Exception $e) {
            return ResponseHelper::exceptionResponse($e);
        }
    }

    public function getCategories(string $username)
    {
        try {
            $store = $this->storeRepository->getByUsername($username);

            if (! $store) {
                return ResponseHelper::jsonResponse(false, 'Store not found', null, 404);
            }

            $categories = $this->storeRepository->getCategories($store->id);

            return ResponseHelper::jsonResponse(true, 'Store Categories Fetched', ProductCategoryResource::collection($categories), 200);
        } catch (\Exception $e) {
            return ResponseHelper::exceptionResponse($e);
        }
    }

    public function updateVerifiedStatus(string $id)
    {
        try {
            // Rute ini cuma di dalam grup auth:sanctum -- tidak ada
            // PermissionMiddleware/HasMiddleware sama sekali untuk action
            // ini (beda dari store/update/destroy di atas), jadi tanpa
            // pengecekan eksplisit ini SIAPA PUN yang login (termasuk buyer
            // tanpa toko) bisa memverifikasi toko mana pun.
            if (! auth()->user()->hasRole('admin')) {
                return ResponseHelper::jsonResponse(false, 'Unauthorized', null, 403);
            }

            $store = $this->storeRepository->getById($id);

            if (! $store) {
                return ResponseHelper::jsonResponse(true, 'Data Toko Tidak Ditemukan', null, 404);
            }

            $store = $this->storeRepository->updateVerifiedStatus($id, true);

            return ResponseHelper::jsonResponse(true, 'Data Toko Berhasil Diverifikasi', new StoreResource($store), 200);
        } catch (\Exception $e) {
            return ResponseHelper::exceptionResponse($e);
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

            if (! $store) {
                return ResponseHelper::jsonResponse(true, 'Data Toko Tidak Ditemukan', null, 404);
            }

            // Sebelumnya tidak ada pengecekan sama sekali -- seller mana
            // pun yang punya permission 'store-edit' (SEMUA seller) bisa
            // update toko MANAPUN via ID, bukan cuma toko sendiri. Pola
            // sama seperti ProductController::update().
            if (! auth()->user()->hasRole('admin') && $store->user_id !== auth()->id()) {
                return ResponseHelper::jsonResponse(false, 'Tidak diizinkan mengubah toko lain', null, 403);
            }

            $store = $this->storeRepository->update($id, $request);
            Cache::tags(['stores'])->flush();

            return ResponseHelper::jsonResponse(true, 'Data Toko Berhasil Diupdate', new StoreResource($store), 200);
        } catch (\Exception $e) {
            return ResponseHelper::exceptionResponse($e);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $store = $this->storeRepository->getById($id);

            if (! $store) {
                return ResponseHelper::jsonResponse(true, 'Data Toko Tidak Ditemukan', null, 404);
            }

            // Sama seperti update() di atas -- sebelumnya seller mana pun
            // bisa MENGHAPUS toko kompetitor lewat ID.
            if (! auth()->user()->hasRole('admin') && $store->user_id !== auth()->id()) {
                return ResponseHelper::jsonResponse(false, 'Tidak diizinkan menghapus toko lain', null, 403);
            }

            $store = $this->storeRepository->delete($id);
            Cache::tags(['stores'])->flush();
            // ProductController::index() cache listing tanpa filter selama
            // 600 detik (Cache::tags(['products'])->remember(...)) --
            // tanpa flush ini, katalog yang sudah warm bisa tetap
            // menampilkan produk toko yang baru dinonaktifkan sampai ±10
            // menit walau stores.is_active sudah false.
            Cache::tags(['products'])->flush();

            return ResponseHelper::jsonResponse(true, 'Data Toko Berhasil Dihapus', new StoreResource($store), 200);
        } catch (\Exception $e) {
            return ResponseHelper::exceptionResponse($e);
        }
    }

    public function registerStore(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:stores,name',
            'phone' => 'required|numeric|regex:/^08[0-9]{8,13}$/',
            'city' => 'nullable|string',
            'address' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $user = Auth::user();

        // Source of truth-nya keberadaan row Store, bukan role 'store'.
        // Admin POST /api/store (lihat store() di atas) membuat Store +
        // StoreBalance tapi TIDAK memberi role 'store' ke target user --
        // jadi buyer yang toko-nya dibuatkan admin masih lolos hasRole()
        // check di sini dan bisa registerStore() lagi, menghasilkan toko
        // kedua persis skenario yang barusan ditutup di store(). Role
        // check dipertahankan sebagai defense tambahan, bukan diganti.
        if ($user->store()->exists() || $user->hasRole('store')) {
            return ResponseHelper::jsonResponse(false, 'Anda sudah memiliki toko.', null, 400);
        }

        try {
            DB::beginTransaction();

            // Create Store
            $store = $user->store()->create([
                'name' => $request->name,
                'username' => Str::slug($request->name).'-'.Str::random(5),
                'phone' => $request->phone ?? $user->buyer?->phone_number,
                'city' => $request->city,
                'address' => $request->address,
                'postal_code' => $request->postal_code,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'is_verified' => false,
                'logo' => '',
                'about' => '-',
                'address_id' => '-',
            ]);

            // Create Store Balance
            $store->storeBalance()->create([
                'balance' => 0,
            ]);

            // Assign 'store' role — buyer role TETAP dipertahankan (dual-role ala Shopee),
            // supaya seller tetap bisa belanja / pakai "Back to Buyer Mode" tanpa 403.
            $user->assignRole('store');

            // Refresh permissions
            $user->permissions = $user->getPermissionsViaRoles()->pluck('name');
            $user->token = $user->createToken('auth_token')->plainTextToken; // Refresh token with new permissions

            DB::commit();

            return ResponseHelper::jsonResponse(true, 'Toko Berhasil Dibuat!', [
                'store' => new StoreResource($store),
                // FE butuh user + token baru (role & permissions berubah ke store)
                'user' => new UserResource($user),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return ResponseHelper::exceptionResponse($e);
        }
    }

    public function followStore(string $id)
    {
        try {
            $user = Auth::user();
            $this->storeRepository->follow($id, $user->id);

            return ResponseHelper::jsonResponse(true, 'Berhasil mengikuti toko', null, 200);
        } catch (\Exception $e) {
            return ResponseHelper::exceptionResponse($e);
        }
    }

    public function unfollowStore(string $id)
    {
        try {
            $user = Auth::user();
            $this->storeRepository->unfollow($id, $user->id);

            return ResponseHelper::jsonResponse(true, 'Berhasil berhenti mengikuti toko', null, 200);
        } catch (\Exception $e) {
            return ResponseHelper::exceptionResponse($e);
        }
    }

    public function checkFollowStatus(string $id)
    {
        try {
            $user = Auth::user();
            if (! $user) {
                return ResponseHelper::jsonResponse(true, 'Not Logged In', ['is_following' => false], 200);
            }
            $status = $this->storeRepository->checkFollowStatus($id, $user->id);

            return ResponseHelper::jsonResponse(true, 'Check Success', ['is_following' => $status], 200);
        } catch (\Exception $e) {
            return ResponseHelper::exceptionResponse($e);
        }
    }

    public function getReviews(string $username)
    {
        try {
            $store = $this->storeRepository->getByUsername($username);
            if (! $store) {
                return ResponseHelper::jsonResponse(false, 'Store not found', null, 404);
            }

            $reviews = $this->storeRepository->getReviews($store->id);

            return ResponseHelper::jsonResponse(true, 'Review Toko Berhasil Diambil', PaginateResource::make($reviews, ProductReviewResource::class), 200);

        } catch (\Exception $e) {
            return ResponseHelper::exceptionResponse($e);
        }
    }
}
