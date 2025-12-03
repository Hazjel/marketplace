<?php

namespace App\Http\Controllers;

use App\Interfaces\StoreBalanceRepositoryInterface;
use Illuminate\Http\Request;
use App\Helpers\ResponseHelper;
use App\Http\Resources\StoreBalanceResource;
use App\Http\Resources\PaginateResource;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class StoreBalanceController extends Controller implements HasMiddleware
{
    private StoreBalanceRepositoryInterface $storeBalanceRepository;

    public function __construct(StoreBalanceRepositoryInterface $storeBalanceRepository)
    {
        $this->storeBalanceRepository = $storeBalanceRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['store-balance-list']), only: ['index', 'getAllPaginated', 'show']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $storeBalances = $this->storeBalanceRepository->getAll($request->search, $request->limit, true);

            return ResponseHelper::jsonResponse(true, 'Data Dompet Toko Berhasil Diambil', StoreBalanceResource::collection($storeBalances), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function getAllPaginated(Request $request)
    {
        $request = $request->validate([
            'search' => 'nullable|string',
            'row_per_page' => 'required|integer'
        ]);

        try {
            $storeBalances = $this->storeBalanceRepository->getAllPaginated($request['search'] ?? null, $request['row_per_page']);

            return ResponseHelper::jsonResponse(true, 'Data Dompet Toko Berhasil Diambil', PaginateResource::make($storeBalances, StoreBalanceResource::class), 200);
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
            $storeBalance = $this->storeBalanceRepository->getById($id);

            if (!$storeBalance) {
                return ResponseHelper::jsonResponse(true, 'Data Dompet Toko Tidak Ditemukan', null, 404);
            }

            return ResponseHelper::jsonResponse(true, 'Data Dompet Toko Berhasil Diambil', new StoreBalanceResource($storeBalance), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

}
