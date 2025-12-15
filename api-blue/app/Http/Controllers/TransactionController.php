<?php

namespace App\Http\Controllers;

use App\Interfaces\TransactionRepositoryInterface;
use App\Models\Transaction;
use App\Repositories\TransactionRepository;
use Illuminate\Http\Request;
use App\Helpers\ResponseHelper;
use App\Http\Requests\TransactionStoreRequest;
use App\Http\Requests\TransactionUpdateRequest;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\PaginateResource;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class TransactionController extends Controller implements HasMiddleware
{
    private TransactionRepositoryInterface $transactionRepository;

    public function __construct(TransactionRepositoryInterface $transactionRepository) {
        $this->transactionRepository = $transactionRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['transaction-list|transaction-create|transaction-edit|transaction-delete']), only: ['index', 'getAllPaginated', 'show']),
            new Middleware(PermissionMiddleware::using(['transaction-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['transaction-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['transaction-delete']), only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $transactions = $this->transactionRepository->getAll($request->search, $request->limit, true);

            return ResponseHelper::jsonResponse(true, 'Data Transaksi Berhasil Diambil', TransactionResource::collection($transactions), 200);
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
            $transactions = $this->transactionRepository->getAllPaginated($request['search'] ?? null, $request['row_per_page']);
            $totalRevenue = $this->transactionRepository->getTotalRevenue();

            $response = PaginateResource::make($transactions, TransactionResource::class);
            $responseData = $response->response()->getData(true);
            $responseData['meta']['total_revenue'] = $totalRevenue;

            // Manual wrapping because using getData(true) returns the array structure directly 
            // but ResponseHelper expects to wrap it in 'data'
            // Wait, ResponseHelper::jsonResponse wraps existing data in 'data'.
            // If I pass $responseData (which has data, meta), ResponseHelper will wrap it AGAIN in 'data'.
            // Making it response.data.data.data...
            // PaginateResource::make returns a resource. JsonResponse helper handles resource.
            // Let's modify the resource using additional()
            
            $resource = PaginateResource::make($transactions, TransactionResource::class)->additional([
                'meta' => [
                    'total_revenue' => $totalRevenue
                ]
            ]);

            return ResponseHelper::jsonResponse(true, 'Data Transaksi Berhasil Diambil', $resource, 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TransactionStoreRequest $request)
    {
        $request = $request->validated();

        try {
            $transaction = $this->transactionRepository->create($request);

            return ResponseHelper::jsonResponse(true, 'Data Transaksi Berhasil Ditambahkan', new TransactionResource($transaction), 201);
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
            $transactions = $this->transactionRepository->getById($id);

            if (!$transactions) {
                return ResponseHelper::jsonResponse(true, 'Data Transaksi Tidak Ditemukan', null, 404);
            }

            return ResponseHelper::jsonResponse(true, 'Data Transaksi Berhasil Diambil', new TransactionResource($transactions), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function showByCode(string $code)
    {
        try {
            $transactions = $this->transactionRepository->getByCode($code);

            if (!$transactions) {
                return ResponseHelper::jsonResponse(true, 'Data Transaksi Tidak Ditemukan', null, 404);
            }

            return ResponseHelper::jsonResponse(true, 'Data Transaksi Berhasil Diambil', new TransactionResource($transactions), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(TransactionUpdateRequest $request, string $id)
    {
        $request = $request->validated();

        try {
            $transactions = $this->transactionRepository->getByid($id);

            if (!$transactions) {
                return ResponseHelper::jsonResponse(true, 'Data Transaksi Tidak Ditemukan', null, 404);
            }

            $transaction = $this->transactionRepository->updateStatus($id, $request);

            return ResponseHelper::jsonResponse(true, 'Data Transaksi Berhasil Diupdate', new TransactionResource($transactions), 200);
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
            $transactions = $this->transactionRepository->getByid($id);

            if (!$transactions) {
                return ResponseHelper::jsonResponse(true, 'Data Transaksi Tidak Ditemukan', null, 404);
            }

            $transaction = $this->transactionRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Data Transaksi Berhasil Dihapus', new TransactionResource($transactions), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }
}
