<?php

namespace App\Http\Controllers;

use App\Events\TransactionStatusUpdated;
use App\Helpers\ResponseHelper;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\TransactionStoreRequest;
use App\Http\Requests\TransactionUpdateRequest;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\TransactionResource;
use App\Interfaces\TransactionAnalyticsRepositoryInterface;
use App\Interfaces\TransactionRepositoryInterface;
use App\Models\Transaction;
use App\Services\MidtransPaymentStatusInterpreter;
use App\Support\OpsSignals;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Midtrans\Config;
use Spatie\Permission\Middleware\PermissionMiddleware;

class TransactionController extends Controller implements HasMiddleware
{
    private TransactionRepositoryInterface $transactionRepository;

    private TransactionAnalyticsRepositoryInterface $transactionAnalyticsRepository;

    public function __construct(
        TransactionRepositoryInterface $transactionRepository,
        TransactionAnalyticsRepositoryInterface $transactionAnalyticsRepository
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->transactionAnalyticsRepository = $transactionAnalyticsRepository;
    }

    public static function middleware()
    {
        return [
            // Removed getAllPaginated from strict permissions list
            new Middleware(PermissionMiddleware::using(['transaction-list|transaction-create|transaction-edit|transaction-delete']), only: ['index', 'show']),
            new Middleware(PermissionMiddleware::using(['transaction-create']), only: ['store', 'checkout']),
            new Middleware(PermissionMiddleware::using(['transaction-edit']), only: ['update', 'cancel']),
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
            return ResponseHelper::exceptionResponse($e);
        }
    }

    public function getAllPaginated(Request $request)
    {
        // Manual Authorization: Allow Admin (permission), or Buyer, or Store
        if (! Auth::user()->can('transaction-list') && ! Auth::user()->hasRole('buyer') && ! Auth::user()->hasRole('store')) {
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
            return ResponseHelper::exceptionResponse($e);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TransactionStoreRequest $request)
    {
        Log::info('Transaction creation started', ['user_id' => Auth::id()]);

        // Security: Prevent Admin from creating transactions
        if ($request->user()->hasRole('admin')) {
            return ResponseHelper::jsonResponse(false, 'Admin forbidden from creating transactions.', null, 403);
        }

        $request = $request->validated();

        try {
            $transaction = $this->transactionRepository->create($request);

            return ResponseHelper::jsonResponse(true, 'Data Transaksi Berhasil Ditambahkan', new TransactionResource($transaction), 201);
        } catch (\Exception $e) {
            return $this->checkoutErrorResponse($e);
        }
    }

    // 422: Saldo Blukios spent by a concurrent checkout; 502: Midtrans could
    // not create the payment (the orders are already cancelled). Else 500.
    private function checkoutErrorResponse(\Exception $e)
    {
        $code = in_array($e->getCode(), [422, 502], true) ? $e->getCode() : 500;

        return ResponseHelper::exceptionResponse($e, $code);
    }

    /**
     * Orders from several stores, paid with one Midtrans payment. Each order
     * comes back as its own transaction sharing payment_code and snap_token.
     */
    public function checkout(CheckoutRequest $request)
    {
        if ($request->user()->hasRole('admin')) {
            return ResponseHelper::jsonResponse(false, 'Admin forbidden from creating transactions.', null, 403);
        }

        $shared = $request->safe()->except(['orders', 'use_balance']);
        $orders = array_map(fn (array $order) => $shared + $order, $request->validated('orders'));

        try {
            $transactions = $this->transactionRepository->checkout($orders, $request->boolean('use_balance'));

            return ResponseHelper::jsonResponse(true, 'Data Transaksi Berhasil Ditambahkan', TransactionResource::collection($transactions), 201);
        } catch (\Exception $e) {
            return $this->checkoutErrorResponse($e);
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

            if (Auth::user()->cannot('view', $transactions)) {
                return ResponseHelper::jsonResponse(false, 'Unauthorized access to this transaction', null, 403);
            }

            return ResponseHelper::jsonResponse(true, 'Data Transaksi Berhasil Diambil', new TransactionResource($transactions), 200);
        } catch (\Exception $e) {
            return ResponseHelper::exceptionResponse($e);
        }
    }

    public function showByCode(string $code)
    {
        try {
            $transactions = $this->transactionRepository->getByCode($code);

            if (! $transactions) {
                return ResponseHelper::jsonResponse(false, 'Data Transaksi Tidak Ditemukan', null, 404);
            }

            if (Auth::user()->cannot('view', $transactions)) {
                return ResponseHelper::jsonResponse(false, 'Unauthorized access to this transaction', null, 403);
            }

            return ResponseHelper::jsonResponse(true, 'Data Transaksi Berhasil Diambil', new TransactionResource($transactions), 200);
        } catch (\Exception $e) {
            return ResponseHelper::exceptionResponse($e);
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

            if (Auth::user()->cannot('update', $transactions)) {
                return ResponseHelper::jsonResponse(false, 'Unauthorized access to this transaction', null, 403);
            }

            $transaction = $this->transactionRepository->updateStatus($id, $request);

            event(new TransactionStatusUpdated($transaction));

            return ResponseHelper::jsonResponse(true, 'Data Transaksi Berhasil Diupdate', new TransactionResource($transaction), 200);
        } catch (\Exception $e) {
            return $this->domainErrorResponse($e);
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
                return ResponseHelper::jsonResponse(false, 'Data Transaksi Tidak Ditemukan', null, 404);
            }

            if (Auth::user()->cannot('complete', $transaction)) {
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
                // public disk, not public/upload: public/ is the host-owned
                // bind mount that www-data cannot write to, which made every
                // completion fail. nginx serves /storage from this disk.
                $receivingProof = 'storage/'.$file->storeAs('transactions', $filename, 'public');
            }

            // Lock + validasi status + rilis escrow dalam satu transaksi --
            // lihat TransactionRepository::completeTransaction(). Pengecekan
            // delivery_status di atas (sebelum ini) hanya penolakan dini yang
            // murah; yang otoritatif adalah pengecekan ulang di dalam lock,
            // supaya dua panggilan complete() yang beririsan (atau beririsan
            // dengan scheduler auto-complete) tidak sama-sama lolos merilis.
            $transaction = $this->transactionRepository->completeTransaction($id, $receivingProof);

            event(new TransactionStatusUpdated($transaction));

            return ResponseHelper::jsonResponse(true, 'Pesanan Selesai — dana telah dirilis ke saldo toko', new TransactionResource($transaction), 200);
        } catch (\Exception $e) {
            return ResponseHelper::exceptionResponse($e, $e->getCode() ?: 500);
        }
    }

    /**
     * Penjual menolak pesanan yang sudah dibayar. Uang pembeli dikembalikan
     * lewat RefundCancelledTransactionJob (Midtrans, atau Saldo Blukios untuk VA).
     */
    public function cancel(Request $request, string $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:255',
        ], [], ['reason' => 'Alasan pembatalan']);

        try {
            $transaction = $this->transactionRepository->getById($id);

            if (! $transaction) {
                return ResponseHelper::jsonResponse(false, 'Data Transaksi Tidak Ditemukan', null, 404);
            }

            if ($request->user()->cannot('cancel', $transaction)) {
                return ResponseHelper::jsonResponse(false, 'Anda tidak memiliki izin untuk membatalkan pesanan ini', null, 403);
            }

            $transaction = $this->transactionRepository->cancelPaidOrder($id, $validated['reason']);

            event(new TransactionStatusUpdated($transaction));

            return ResponseHelper::jsonResponse(true, 'Pesanan dibatalkan, dana pembeli sedang dikembalikan', new TransactionResource($transaction), 200);
        } catch (\Exception $e) {
            return $this->domainErrorResponse($e);
        }
    }

    /**
     * Pembeli mengisi rekening tujuan refund manual (pesanan lama, sebelum
     * refund VA masuk ke Saldo Blukios).
     */
    public function refundAccount(Request $request, string $id)
    {
        $validated = $request->validate([
            'refund_bank_name' => 'required|string|max:100',
            'refund_account_number' => 'required|string|regex:/^[0-9]{5,30}$/',
            'refund_account_name' => 'required|string|max:100',
        ], [], [
            'refund_bank_name' => 'Nama Bank',
            'refund_account_number' => 'Nomor Rekening',
            'refund_account_name' => 'Nama Pemilik Rekening',
        ]);

        try {
            $transaction = $this->transactionRepository->getById($id);

            if (! $transaction) {
                return ResponseHelper::jsonResponse(false, 'Data Transaksi Tidak Ditemukan', null, 404);
            }

            if ($request->user()->cannot('submitRefundAccount', $transaction)) {
                return ResponseHelper::jsonResponse(false, 'Anda tidak memiliki izin untuk melakukan aksi ini', null, 403);
            }

            $transaction = $this->transactionRepository->saveRefundAccount($id, $validated);

            return ResponseHelper::jsonResponse(true, 'Rekening refund tersimpan', new TransactionResource($transaction), 200);
        } catch (\Exception $e) {
            return $this->domainErrorResponse($e);
        }
    }

    /**
     * Admin mencatat refund manual yang sudah ditransfer.
     */
    public function markRefunded(Request $request, string $id)
    {
        $validated = $request->validate([
            'note' => 'required|string|max:255',
        ], [], ['note' => 'Catatan transfer']);

        try {
            $transaction = $this->transactionRepository->getById($id);

            if (! $transaction) {
                return ResponseHelper::jsonResponse(false, 'Data Transaksi Tidak Ditemukan', null, 404);
            }

            if ($request->user()->cannot('markRefunded', $transaction)) {
                return ResponseHelper::jsonResponse(false, 'Anda tidak memiliki izin untuk melakukan aksi ini', null, 403);
            }

            $transaction = $this->transactionRepository->markRefundTransferred($id, $validated['note']);

            event(new TransactionStatusUpdated($transaction));

            return ResponseHelper::jsonResponse(true, 'Refund ditandai selesai', new TransactionResource($transaction), 200);
        } catch (\Exception $e) {
            return $this->domainErrorResponse($e);
        }
    }

    /**
     * Admin mengembalikan refund manual lama ke Saldo Blukios pembeli.
     */
    public function refundToBalance(Request $request, string $id)
    {
        $validated = $request->validate([
            'note' => 'nullable|string|max:255',
        ], [], ['note' => 'Catatan']);

        try {
            $transaction = $this->transactionRepository->getById($id);

            if (! $transaction) {
                return ResponseHelper::jsonResponse(false, 'Data Transaksi Tidak Ditemukan', null, 404);
            }

            if ($request->user()->cannot('markRefunded', $transaction)) {
                return ResponseHelper::jsonResponse(false, 'Anda tidak memiliki izin untuk melakukan aksi ini', null, 403);
            }

            $transaction = $this->transactionRepository->refundToBalance(
                $id,
                $validated['note'] ?? 'Dikembalikan ke Saldo Blukios oleh admin',
                'manual_required',
            );

            if (! $transaction) {
                return ResponseHelper::jsonResponse(false, 'Pesanan ini tidak sedang menunggu refund manual', null, 422);
            }

            event(new TransactionStatusUpdated($transaction));

            return ResponseHelper::jsonResponse(true, 'Dana dikembalikan ke Saldo Blukios', new TransactionResource($transaction), 200);
        } catch (\Exception $e) {
            return $this->domainErrorResponse($e);
        }
    }

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

            if (Auth::user()->cannot('checkPaymentStatus', $transaction)) {
                return ResponseHelper::jsonResponse(false, 'Unauthorized access to this transaction', null, 403);
            }

            // Configure Midtrans
            Config::$serverKey = config('midtrans.serverKey');
            Config::$isProduction = config('midtrans.isProduction');
            Config::$isSanitized = config('midtrans.isSanitized');
            Config::$is3ds = config('midtrans.is3ds');

            $mongoAdjustments = [];
            $lateRefunds = [];

            // Saldo Blukios covered the whole payment: Midtrans never saw it.
            if (Transaction::midtransTotal(Transaction::inPayment($transaction->paymentCode())->get())->isZero()) {
                return ResponseHelper::jsonResponse(true, 'Dibayar dengan Saldo Blukios', new TransactionResource($transaction), 200);
            }

            try {
                // Panggilan keluar ke Midtrans TIDAK boleh terjadi sambil
                // memegang row lock -- itu menahan lock selama durasi round-trip
                // HTTP, mengunci request lain ke transaksi ini selama itu.
                // Dilakukan dulu di luar transaksi, hasilnya baru dipakai di
                // dalam blok terkunci di bawah.
                // A multi-store checkout is one Midtrans payment under payment_code.
                $midtransStatus = \Midtrans\Transaction::status($transaction->paymentCode());

                $transactionStatus = $midtransStatus->transaction_status;
                $paymentType = $midtransStatus->payment_type;
                $fraudStatus = $midtransStatus->fraud_status;
                $grossAmount = ((object) $midtransStatus)->gross_amount ?? null;

                // Sebelumnya blok ini membaca-putuskan-simpan tanpa DB::transaction
                // atau lockForUpdate sama sekali -- webhook Midtrans dan endpoint
                // manual ini bisa saling tumpang tindih pada transaksi yang sama
                // dan sama-sama lolos mengkredit escrow. EscrowRepository::credit()
                // sekarang membungkus dirinya sendiri, tapi lock di sini tetap
                // perlu supaya keputusan "apakah perlu update" konsisten dengan apa
                // yang benar-benar tersimpan saat lock didapat, bukan snapshot basi
                // dari sebelum panggilan Midtrans.
                //
                // DB::transaction(closure) TIDAK dipakai di sini dengan sengaja --
                // ia rollback SQL (melepas row lock Transaction) SEBELUM exception
                // sampai ke catch, jadi kompensasi Mongo di catch itu selalu
                // terlambat. begin/commit/rollBack manual + catch bersarang di
                // bawah menjamin kompensasi jalan SAAT lock masih dipegang --
                // lihat docblock TransactionRepository::restoreStock(). catch
                // ini rethrow (bukan menangani sendiri) supaya tetap jatuh ke
                // catch terluar yang mengembalikan response "Gagal cek Midtrans"
                // seperti sebelumnya, tanpa mengompensasi dua kali di sana.
                DB::beginTransaction();

                try {
                    // The same status applies to every order paid under it.
                    $group = Transaction::inPayment($transaction->paymentCode())->orderBy('id')->lockForUpdate()->get();

                    // Same check as the webhook: only the Midtrans part is paid there.
                    $amountMatches = (int) round((float) $grossAmount) === Transaction::midtransTotal($group)->minor();

                    $newStatus = MidtransPaymentStatusInterpreter::interpret($transactionStatus, $paymentType, $fraudStatus);
                    if ($newStatus === 'paid' && ! $amountMatches) {
                        Log::error('Midtrans amount mismatch (cek manual)', [
                            'payment' => $transaction->paymentCode(),
                            'received' => $grossAmount,
                        ]);
                        $newStatus = null;
                    }

                    $toFail = [];
                    foreach ($group as $locked) {
                        // Transaksi yang sudah paid tidak boleh mundur -- webhook yang
                        // telat atau panggilan manual yang beririsan tidak boleh
                        // membatalkan pembayaran yang sudah dikredit ke escrow.
                        if ($newStatus === null || $locked->refund_status !== null) {
                            // no-op -- status Midtrans tanpa arti, atau dibatalkan
                            // penjual setelah bayar; Midtrans masih "settlement"
                            // sampai refund diproses, dan itu tidak boleh
                            // mengkredit escrow lagi.
                        } elseif ($locked->payment_status === 'failed') {
                            // Terminal: only money that still arrives matters, refunded.
                            if ($newStatus === 'paid' && $this->transactionRepository->refundLatePayment($locked)) {
                                $lateRefunds[] = $locked;
                            }
                        } elseif ($locked->payment_status === 'paid') {
                            // no-op -- biarkan $locked apa adanya
                        } elseif ($newStatus === 'paid') {
                            $this->transactionRepository->markPaid($locked);
                        } elseif ($newStatus === 'failed') {
                            $toFail[] = $locked;
                        } elseif ($newStatus !== $locked->payment_status) {
                            $locked->payment_status = $newStatus;
                            $locked->save();
                        }
                    }
                    // All stock, then all balance returns (buyer lock last).
                    $this->transactionRepository->failPayment($toFail, $mongoAdjustments);

                    DB::commit();
                    $transaction = $group->firstWhere('id', $id) ?? $transaction;
                } catch (\Throwable $e) {
                    // Kompensasi SEBELUM rollback -- lihat docblock restoreStock().
                    $this->transactionRepository->compensateStockRestoreRollback($mongoAdjustments);
                    DB::rollBack();

                    throw $e;
                }

                foreach ($group as $member) {
                    event(new TransactionStatusUpdated($member->fresh()));
                }
                foreach ($lateRefunds as $late) {
                    OpsSignals::record(OpsSignals::LATE_PAYMENT_REFUNDED, $late->code);
                    $this->transactionRepository->startRefund($late);
                }

                return ResponseHelper::jsonResponse(true, 'Status Payment Berhasil Diupdate', new TransactionResource($transaction), 200);

            } catch (\Exception $e) {
                // Midtrans unreachable or unaware of the order yet, or the
                // locked block above failed (already compensated and rolled
                // back): nothing changed, and the caller must not read success.
                Log::error('Cek status Midtrans gagal', [
                    'transaction' => $transaction->code,
                    'exception' => $e,
                ]);

                return ResponseHelper::exceptionResponse($e, 502);
            }

        } catch (\Exception $e) {
            return ResponseHelper::exceptionResponse($e);
        }
    }

    public function destroy(string $id)
    {
        try {
            $transactions = $this->transactionRepository->getById($id);

            if (! $transactions) {
                return ResponseHelper::jsonResponse(false, 'Data Transaksi Tidak Ditemukan', null, 404);
            }

            if (Auth::user()->cannot('delete', $transactions)) {
                return ResponseHelper::jsonResponse(false, 'Unauthorized access to this transaction', null, 403);
            }

            $transaction = $this->transactionRepository->delete($id);

            return ResponseHelper::jsonResponse(true, 'Data Transaksi Berhasil Dihapus', new TransactionResource($transactions), 200);
        } catch (\Exception $e) {
            return $this->domainErrorResponse($e);
        }
    }
}
