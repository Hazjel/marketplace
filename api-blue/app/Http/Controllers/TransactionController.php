<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\TransactionStoreRequest;
use App\Http\Requests\TransactionUpdateRequest;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\TransactionResource;
use App\Interfaces\EscrowRepositoryInterface;
use App\Interfaces\TransactionAnalyticsRepositoryInterface;
use App\Interfaces\TransactionRepositoryInterface;
use App\Models\Transaction;
use App\Services\MidtransPaymentStatusInterpreter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Midtrans\Config;
use Spatie\Permission\Middleware\PermissionMiddleware;

class TransactionController extends Controller implements HasMiddleware
{
    private TransactionRepositoryInterface $transactionRepository;

    private TransactionAnalyticsRepositoryInterface $transactionAnalyticsRepository;

    private EscrowRepositoryInterface $escrowRepository;

    public function __construct(
        TransactionRepositoryInterface $transactionRepository,
        TransactionAnalyticsRepositoryInterface $transactionAnalyticsRepository,
        EscrowRepositoryInterface $escrowRepository
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->transactionAnalyticsRepository = $transactionAnalyticsRepository;
        $this->escrowRepository = $escrowRepository;
    }

    public static function middleware()
    {
        return [
            // Removed getAllPaginated from strict permissions list
            new Middleware(PermissionMiddleware::using(['transaction-list|transaction-create|transaction-edit|transaction-delete']), only: ['index', 'show']),
            new Middleware(PermissionMiddleware::using(['transaction-create']), only: ['store']),
            new Middleware(PermissionMiddleware::using(['transaction-edit']), only: ['update']),
            new Middleware(PermissionMiddleware::using(['transaction-delete']), only: ['destroy']),
            new Middleware('auth:sanctum', only: ['complete']),
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
        // Manual Authorization: Allow Admin (permission), or Buyer, or Store
        if (! auth()->user()->can('transaction-list') && ! auth()->user()->hasRole('buyer') && ! auth()->user()->hasRole('store')) {
            return ResponseHelper::jsonResponse(false, 'Unauthorized', null, 403);
        }

        $request = $request->validate([
            'search' => 'nullable|string',
            'row_per_page' => 'required|integer|min:1|max:100',
        ]);

        try {
            $mode = request('mode');
            $transactions = $this->transactionRepository->getAllPaginated($request['search'] ?? null, $request['row_per_page']);
            $totalRevenue = $this->transactionAnalyticsRepository->getTotalRevenue($mode);
            $totalAdminFee = $this->transactionAnalyticsRepository->getTotalAdminFee($mode);

            // Manual wrapping because using getData(true) returns the array structure directly
            // but ResponseHelper expects to wrap it in 'data'
            // Wait, ResponseHelper::jsonResponse wraps existing data in 'data'.
            // If I pass $responseData (which has data, meta), ResponseHelper will wrap it AGAIN in 'data'.
            // Making it response.data.data.data...
            // PaginateResource::make returns a resource. JsonResponse helper handles resource.
            // Let's modify the resource using additional()

            // Use 'new' because 'make' static method often only accepts one arg, causing resourceClass to be null
            $resource = (new PaginateResource($transactions, TransactionResource::class))->additional([
                'meta' => [
                    'total_revenue' => $totalRevenue,
                    'total_admin_fee' => $totalAdminFee,
                ],
            ]);

            return ResponseHelper::jsonResponse(true, 'Data Transaksi Berhasil Diambil', $resource, 200);
        } catch (\Throwable $e) {
            Log::error('Transaction list error', ['error' => $e->getMessage()]);

            return ResponseHelper::jsonResponse(false, 'Terjadi kesalahan pada server.', null, 500);
        }
    }

    public function getChartData(Request $request)
    {
        $days = (int) $request->query('days', 7);
        if (! in_array($days, [7, 30, 90], true)) {
            $days = 7;
        }

        try {
            $data = $this->transactionAnalyticsRepository->getChartData($days, request('mode'));

            return ResponseHelper::jsonResponse(true, 'success', $data, 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TransactionStoreRequest $request)
    {
        Log::info('Transaction creation started', ['user_id' => auth()->id()]);

        // Security: Prevent Admin from creating transactions
        if ($request->user()->hasRole('admin')) {
            return ResponseHelper::jsonResponse(false, 'Admin forbidden from creating transactions.', null, 403);
        }

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
            Log::info('SHOW TX ID: '.$id);
            $transactions = $this->transactionRepository->getById($id);

            if (! $transactions) {
                Log::error('SHOW TX: Not Found for ID '.$id);

                return ResponseHelper::jsonResponse(false, 'Data Transaksi Tidak Ditemukan', null, 404);
            }

            // Security: Prevent IDOR (Only Buyer, Seller, or Admin can view).
            // User bisa dual-role (buyer + store) -- akses sah kalau dia buyer
            // ATAU seller dari transaksi ini, bukan harus lolos kedua guard
            // sekaligus (dual-role buyer yang beli dari toko lain akan selalu
            // gagal guard store kalau dicek independen).
            $user = auth()->user();
            $isOwningBuyer = $user->hasRole('buyer') && $transactions->buyer_id === $user->buyer?->id;
            $isOwningStore = $user->hasRole('store') && $transactions->store_id === $user->store?->id;
            $isAdmin = $user->hasRole('admin');

            if (! $isOwningBuyer && ! $isOwningStore && ! $isAdmin) {
                return ResponseHelper::jsonResponse(false, 'Unauthorized access to this transaction', null, 403);
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

            if (! $transactions) {
                return ResponseHelper::jsonResponse(false, 'Data Transaksi Tidak Ditemukan', null, 404);
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
            $transactions = $this->transactionRepository->getById($id);

            if (! $transactions) {
                return ResponseHelper::jsonResponse(false, 'Data Transaksi Tidak Ditemukan', null, 404);
            }

            $transaction = $this->transactionRepository->updateStatus($id, $request);

            return ResponseHelper::jsonResponse(true, 'Data Transaksi Berhasil Diupdate', new TransactionResource($transaction), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Complete the transaction (Buyer only).
     * Releases pending_balance to available balance (escrow release).
     */
    public function complete(string $id)
    {
        try {
            $transaction = $this->transactionRepository->getById($id);

            if (! $transaction) {
                return ResponseHelper::jsonResponse(true, 'Data Transaksi Tidak Ditemukan', null, 404);
            }

            // Authorization: Ensure user is the buyer
            $user = auth()->user();
            if (! $user->buyer || $transaction->buyer_id !== $user->buyer->id) {
                return ResponseHelper::jsonResponse(false, 'Unauthorized', null, 403);
            }

            if ($transaction->delivery_status !== 'delivering') {
                return ResponseHelper::jsonResponse(false, 'Hanya status delivering yang bisa diselesaikan', null, 400);
            }

            $validation = Validator::make(request()->all(), [
                'receiving_proof' => 'required|image|max:2048',
            ]);

            if ($validation->fails()) {
                return ResponseHelper::jsonResponse(false, $validation->errors()->first(), $validation->errors(), 422);
            }

            $receivingProof = null;
            if (request()->hasFile('receiving_proof')) {
                $file = request()->file('receiving_proof');
                $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $mime = $file->getMimeType();
                if (! array_key_exists($mime, $allowedMimes)) {
                    return ResponseHelper::jsonResponse(false, 'Tipe file tidak diizinkan.', null, 422);
                }
                $filename = time().'_'.Str::random(16).'.'.$allowedMimes[$mime];
                $file->move(public_path('upload/transactions'), $filename);
                $receivingProof = 'upload/transactions/'.$filename;
            }

            $transaction = $this->transactionRepository->updateStatus($id, [
                'delivery_status' => 'completed',
                'receiving_proof' => $receivingProof,
            ]);

            // ESCROW RELEASE: Pindahkan dana dari pending_balance ke available balance
            $this->escrowRepository->release($transaction);

            return ResponseHelper::jsonResponse(true, 'Pesanan Selesai — dana telah dirilis ke saldo toko', new TransactionResource($transaction), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    /**
     * Check payment status from Midtrans manually (for localhost/sync).
     */
    public function checkPaymentStatus(string $id)
    {
        try {
            $transaction = $this->transactionRepository->getById($id);

            if (! $transaction) {
                return ResponseHelper::jsonResponse(true, 'Data Transaksi Tidak Ditemukan', null, 404);
            }

            // Authorization: prevent IDOR — only the buyer, the seller, or admin can check.
            // Sama seperti show(): user dual-role harus lolos SALAH SATU guard, bukan keduanya.
            $user = auth()->user();
            $isOwningBuyer = $user->hasRole('buyer') && $transaction->buyer_id === $user->buyer?->id;
            $isOwningStore = $user->hasRole('store') && $transaction->store_id === $user->store?->id;
            $isAdmin = $user->hasRole('admin');

            if (! $isOwningBuyer && ! $isOwningStore && ! $isAdmin) {
                return ResponseHelper::jsonResponse(false, 'Unauthorized access to this transaction', null, 403);
            }

            // Configure Midtrans
            Config::$serverKey = config('midtrans.serverKey');
            Config::$isProduction = config('midtrans.isProduction');
            Config::$isSanitized = config('midtrans.isSanitized');
            Config::$is3ds = config('midtrans.is3ds');

            try {
                // Fetch status from Midtrans
                $midtransStatus = \Midtrans\Transaction::status($transaction->code);

                $transactionStatus = $midtransStatus->transaction_status;
                $paymentType = $midtransStatus->payment_type;
                $fraudStatus = $midtransStatus->fraud_status;

                // Interpreter sama dengan yang dipakai webhook callback — no-op (null)
                // berarti status tetap seperti sekarang (mis. capture non-credit_card).
                $newStatus = MidtransPaymentStatusInterpreter::interpret($transactionStatus, $paymentType, $fraudStatus)
                    ?? $transaction->payment_status;

                if ($newStatus === 'paid' && $transaction->payment_status !== 'paid') {
                    $transaction->payment_status = 'paid';
                    $transaction->save();

                    // Credit ke pending_balance (escrow) — sama seperti Midtrans callback
                    $this->escrowRepository->credit($transaction);
                } elseif ($newStatus !== $transaction->payment_status) {
                    $transaction->payment_status = $newStatus;
                    $transaction->save();

                    // Restore stock if payment failed/expired/cancelled
                    if (in_array($newStatus, ['failed', 'cancelled', 'expired'])) {
                        $this->transactionRepository->restoreStock($transaction);
                    }
                }

                return ResponseHelper::jsonResponse(true, 'Status Payment Berhasil Diupdate', new TransactionResource($transaction), 200);

            } catch (\Exception $e) {
                // If Midtrans throws error (e.g. transaction not found there yet), Just return current.
                return ResponseHelper::jsonResponse(true, 'Gagal cek Midtrans: '.$e->getMessage(), new TransactionResource($transaction), 200);
            }

        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $transactions = $this->transactionRepository->getById($id);

            if (! $transactions) {
                return ResponseHelper::jsonResponse(false, 'Data Transaksi Tidak Ditemukan', null, 404);
            }

            $transaction = $this->transactionRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Data Transaksi Berhasil Dihapus', new TransactionResource($transactions), 200);
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 500);
        }
    }
}
