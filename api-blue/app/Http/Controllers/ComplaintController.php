<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\TransactionResource;
use App\Interfaces\TransactionRepositoryInterface;
use App\Models\Complaint;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Komplain pembeli atas pesanan yang sedang dikirim. Setiap respons berisi
 * pesanannya (TransactionResource, dengan komplain di dalamnya).
 */
class ComplaintController extends Controller
{
    public function __construct(private TransactionRepositoryInterface $transactionRepository) {}

    public function store(Request $request, string $id)
    {
        $validated = $request->validate([
            'reason' => ['required', Rule::in(Complaint::REASONS)],
            'description' => 'required|string|min:10|max:1000',
            'photos' => 'nullable|array|max:3',
            'photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [], ['reason' => 'Alasan', 'description' => 'Keterangan', 'photos' => 'Foto']);

        try {
            $transaction = $this->transactionRepository->getById($id);

            if (! $transaction) {
                return ResponseHelper::jsonResponse(false, 'Data Transaksi Tidak Ditemukan', null, 404);
            }

            if ($request->user()->cannot('complain', $transaction)) {
                return ResponseHelper::jsonResponse(false, 'Anda tidak memiliki izin untuk melakukan aksi ini', null, 403);
            }

            $transaction = $this->transactionRepository->createComplaint(
                $id,
                ['reason' => $validated['reason'], 'description' => $validated['description']],
                array_values($request->file('photos', [])),
            );

            return ResponseHelper::jsonResponse(true, 'Komplain terkirim, penjual punya 2 hari untuk menanggapi', new TransactionResource($transaction), 201);
        } catch (\Exception $e) {
            return $this->domainErrorResponse($e);
        }
    }

    public function withdraw(Request $request, string $id)
    {
        return $this->move($request, $id, 'complain', Complaint::ACTIVE, 'withdrawn', [
            'resolved_by' => $request->user()->id,
        ], 'Komplain ditarik');
    }

    public function accept(Request $request, string $id)
    {
        return $this->move($request, $id, 'respondToComplaint', ['open'], 'approved', [
            'resolved_by' => $request->user()->id,
        ], 'Komplain diterima, dana pembeli sedang dikembalikan');
    }

    public function reject(Request $request, string $id)
    {
        $validated = $request->validate([
            'response' => 'required|string|min:5|max:1000',
        ], [], ['response' => 'Tanggapan']);

        return $this->move($request, $id, 'respondToComplaint', ['open'], 'escalated', [
            'seller_response' => $validated['response'],
        ], 'Komplain diteruskan ke admin');
    }

    public function resolve(Request $request, string $id)
    {
        $validated = $request->validate([
            'outcome' => 'required|in:approve,reject',
            'note' => 'required|string|min:5|max:1000',
        ], [], ['outcome' => 'Keputusan', 'note' => 'Catatan']);

        $approve = $validated['outcome'] === 'approve';

        return $this->move($request, $id, 'resolveComplaint', ['escalated'], $approve ? 'approved' : 'rejected', [
            'admin_note' => $validated['note'],
            'resolved_by' => $request->user()->id,
        ], $approve ? 'Komplain disetujui, dana pembeli sedang dikembalikan' : 'Komplain ditolak');
    }

    /** Admin queue: orders whose complaint has ?status= (default escalated), oldest complaint first. */
    public function index(Request $request)
    {
        if ($request->user()->cannot('resolveComplaint', Transaction::class)) {
            return ResponseHelper::jsonResponse(false, 'Anda tidak memiliki izin untuk melakukan aksi ini', null, 403);
        }

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'escalated', 'approved', 'rejected', 'withdrawn'])],
            'row_per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $status = $validated['status'] ?? 'escalated';
        $transactions = Transaction::with('complaint')
            ->whereHas('complaint', fn ($query) => $query->where('status', $status))
            ->orderBy(Complaint::select('created_at')->whereColumn('complaints.transaction_id', 'transactions.id'))
            ->paginate($validated['row_per_page'] ?? 20);
        EloquentCollection::make($transactions->items())->load(['buyer.user', 'store', 'transactionDetails.product']);

        return ResponseHelper::jsonResponse(true, 'Data Komplain Berhasil Diambil', new PaginateResource($transactions, TransactionResource::class), 200);
    }

    /**
     * @param  list<string>  $from
     * @param  array<string, mixed>  $changes
     */
    private function move(Request $request, string $id, string $ability, array $from, string $to, array $changes, string $message)
    {
        try {
            $transactionId = Complaint::where('id', $id)->value('transaction_id');
            $transaction = $transactionId ? Transaction::find($transactionId) : null;

            if (! $transaction) {
                return ResponseHelper::jsonResponse(false, 'Komplain tidak ditemukan', null, 404);
            }

            if ($request->user()->cannot($ability, $transaction)) {
                return ResponseHelper::jsonResponse(false, 'Anda tidak memiliki izin untuk melakukan aksi ini', null, 403);
            }

            $transaction = $this->transactionRepository->moveComplaint($id, $from, $to, $changes);

            return ResponseHelper::jsonResponse(true, $message, new TransactionResource($transaction), 200);
        } catch (\Exception $e) {
            return $this->domainErrorResponse($e);
        }
    }
}
