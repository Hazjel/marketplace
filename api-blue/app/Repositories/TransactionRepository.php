<?php

namespace App\Repositories;

use App\Events\TransactionStatusUpdated;
use App\Interfaces\BuyerBalanceRepositoryInterface;
use App\Interfaces\EscrowRepositoryInterface;
use App\Interfaces\PaymentGatewayInterface;
use App\Interfaces\ShippingGatewayInterface;
use App\Interfaces\TransactionRepositoryInterface;
use App\Jobs\RefundCancelledTransactionJob;
use App\Models\BuyerBalanceHistory;
use App\Models\Complaint;
use App\Models\Product;
use App\Models\ProductVariantMongo;
use App\Models\Store;
use App\Models\StoreBalance;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Support\BusinessMetrics;
use App\Support\ComplaintAlerts;
use App\ValueObjects\Money;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TransactionRepository implements TransactionRepositoryInterface
{
    public function __construct(
        private EscrowRepositoryInterface $escrowRepository,
        private PaymentGatewayInterface $paymentGateway,
        private ShippingGatewayInterface $shippingGateway,
        private BuyerBalanceRepositoryInterface $buyerBalanceRepository
    ) {}

    public function getAll(?string $search, ?int $limit, bool $execute)
    {
        $mode = request('mode');

        $query = Transaction::with([
            'buyer.user',
            'store',
            'transactionDetails.product.store',
            'transactionDetails.product.productCategory',
            'transactionDetails.product.productImages',
            'complaint',
        ])
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->search($search);
                }
            });

        // User bisa dual-role (buyer + store) sejak dukung mode ganda ala
        // Shopee — $mode dari FE (?mode=store|buyer) menentukan KONTEKS,
        // scopeToMode tidak asumsikan role eksklusif seperti sebelumnya.
        if (! (Auth::check() && Auth::user()->hasRole('admin'))) {
            $this->scopeToMode($query, $mode);
        }

        // Antrean refund manual admin: ?refund_status=manual_required.
        $refundStatus = request('refund_status');
        if (in_array($refundStatus, ['processing', 'manual_required', 'refunded'], true)) {
            $query->where('refund_status', $refundStatus);
        }

        $query->orderBy('created_at', 'desc');

        if ($limit) {
            $query->take($limit);
        }

        if ($execute) {
            return $query->get();
        }

        return $query;
    }

    public function getAllPaginated(?string $search, ?int $rowPerPage)
    {
        $query = $this->getAll($search, null, false);

        return $query->paginate($rowPerPage);
    }

    /**
     * Scope query ke store_id/buyer_id milik user login, berdasar $mode eksplisit.
     * Wajib dipakai (bukan cek hasRole berurutan) karena user bisa dual-role
     * (buyer + store sekaligus, sejak dukung mode ganda ala Shopee) — hasRole
     * saja tak cukup untuk tahu KONTEKS mana yang diminta caller.
     * Return false kalau user tidak punya akses ke mode yang diminta.
     */
    private function scopeToMode($query, ?string $mode): bool
    {
        if (! Auth::check()) {
            return false;
        }
        $user = Auth::user();

        if ($mode === 'store') {
            if (! $user->hasRole('store') || ! $user->store) {
                return false;
            }
            $query->where('store_id', $user->store->id);

            return true;
        }

        if ($mode === 'buyer') {
            if (! $user->hasRole('buyer') || ! $user->buyer) {
                return false;
            }
            $query->where('buyer_id', $user->buyer->id);

            return true;
        }

        // Tanpa mode eksplisit: admin lihat semua, non-admin default ke
        // scope store (prioritas) lalu buyer — pola lama dipertahankan
        // untuk pemanggil yang belum di-migrasi ke $mode eksplisit.
        if ($user->hasRole('admin')) {
            return true;
        }
        if ($user->hasRole('store') && $user->store) {
            $query->where('store_id', $user->store->id);

            return true;
        }
        if ($user->hasRole('buyer') && $user->buyer) {
            $query->where('buyer_id', $user->buyer->id);

            return true;
        }

        return false;
    }

    public function getById(string $id)
    {
        $query = Transaction::where('id', $id)->with([
            'transactionDetails.product.productImages',
            'productReviews.user',
            'productReviews.attachments',
            'complaint',
        ]);

        return $query->first();
    }

    public function getByCode(string $code)
    {
        $query = Transaction::where('code', $code);

        return $query->first();
    }

    /**
     * Pembeli selalu user yang sedang login. Tidak ada alasan sah bagi client
     * untuk menentukan siapa yang berbelanja.
     */
    private function resolveBuyerId(): string
    {
        $buyer = Auth::user()?->buyer;

        if (! $buyer) {
            throw new Exception('Akun ini belum memiliki profil pembeli.');
        }

        return $buyer->id;
    }

    /**
     * Toko diturunkan dari produk yang dibeli, bukan dari payload, sekaligus
     * menegakkan aturan satu transaksi hanya berisi produk dari satu toko.
     * Tanpa ini sebuah pesanan bisa mencampur produk lintas toko sementara
     * pembayarannya hanya masuk ke satu saldo penjual.
     */
    private function resolveStoreId(array $products): string
    {
        $productIds = array_column($products, 'product_id');

        $storeIds = Product::whereIn('id', $productIds)
            ->distinct()
            ->pluck('store_id');

        if ($storeIds->isEmpty()) {
            throw new Exception('Produk tidak ditemukan.');
        }

        if ($storeIds->count() > 1) {
            throw new Exception('Semua produk harus berasal dari toko yang sama.');
        }

        $storeId = $storeIds->first();

        // Katalog publik sudah menyaring toko nonaktif, tapi cart bisa
        // menyimpan produk yang ditambahkan sebelum pemilik toko menghapus
        // akunnya. Jangan sampai checkout tetap lolos lewat jalur itu.
        if (! Store::where('id', $storeId)->where('is_active', true)->exists()) {
            throw new Exception('Toko ini sudah tidak aktif.');
        }

        return $storeId;
    }

    /**
     * Ongkir diambil ulang dari gateway, bukan dari payload.
     *
     * Sebelumnya nilai ini dipakai apa adanya dari client, jadi pembeli bisa
     * mengirim shipping_cost 0 dan tetap checkout. Yang dipercaya sekarang cuma
     * PILIHAN kurirnya; harganya ditentukan server dengan memanggil gateway
     * memakai asal (alamat toko), tujuan, dan berat yang dihitung sendiri.
     *
     * Gateway meng-cache satu jam per kombinasi asal/tujuan/berat, dan pembeli
     * baru saja memuat daftar ini saat memilih kurir, jadi hampir selalu kena
     * cache dan tidak menambah panggilan keluar.
     */
    private function resolveShippingCost(array $data): int
    {
        $store = Store::find($data['store_id']);
        $originId = (int) ($store?->address_id ?? 0);
        $destinationId = (int) ($data['address_id'] ?? 0);

        if ($originId <= 0 || $destinationId <= 0) {
            throw new Exception('Alamat pengiriman atau alamat toko belum lengkap.');
        }

        $couriers = $this->shippingGateway->calculateCosts(
            $originId,
            $destinationId,
            $this->totalWeightGrams($data['products']),
            $data['city'] ?? null
        );

        foreach ($couriers as $courier) {
            $sameCourier = strcasecmp((string) ($courier['shipping_name'] ?? ''), (string) $data['shipping']) === 0;
            $sameService = strcasecmp((string) ($courier['service_name'] ?? ''), (string) $data['shipping_type']) === 0;

            if ($sameCourier && $sameService) {
                return (int) round((float) ($courier['shipping_cost_net'] ?? 0));
            }
        }

        // Gagal di sini lebih baik daripada diam-diam memakai angka kiriman
        // client: kalau opsinya tidak ada, harganya tidak bisa dipertanggungjawabkan.
        throw new Exception('Opsi pengiriman yang dipilih tidak tersedia. Silakan pilih ulang kurir.');
    }

    /**
     * Berat produk tersimpan dalam kg; gateway meminta gram, dengan lantai
     * yang sama seperti ShipmentController supaya harganya konsisten dengan
     * yang tadi ditampilkan ke pembeli.
     */
    private function totalWeightGrams(array $products): int
    {
        $weights = Product::whereIn('id', array_column($products, 'product_id'))
            ->pluck('weight', 'id');

        $totalKg = 0.0;
        foreach ($products as $item) {
            $totalKg += ((float) ($weights[$item['product_id']] ?? 0)) * (int) $item['qty'];
        }

        return max(100, (int) round($totalKg * 1000));
    }

    /**
     * Kalau produk punya varian, variant_id WAJIB ada dan harus benar-benar
     * milik produk itu -- diam-diam jatuh ke null (lalu memakai
     * products.price/stock, yaitu varian termurah) persis bug yang lagi
     * ditutup di sini, bukan fallback yang aman.
     */
    private function resolveVariant(Product $product, ?string $variantId): ?ProductVariantMongo
    {
        if (! $product->has_variants) {
            return null;
        }

        if (! $variantId) {
            throw new Exception("Produk {$product->name} punya varian -- pilih varian terlebih dahulu.");
        }

        $variant = ProductVariantMongo::find($variantId);

        if (! $variant || $variant->product_id !== $product->id) {
            throw new Exception('Varian yang dipilih tidak ditemukan.');
        }

        return $variant;
    }

    /**
     * Kompensasi manual mutasi stok varian Mongo setelah operasi SQL yang
     * memuatnya gagal. MongoDB tidak ikut DB::beginTransaction()/
     * DB::transaction() Laravel -- koneksi terpisah, bukan distributed
     * transaction -- jadi rollback otomatis Laravel cuma membatalkan sisi
     * Postgres. Kalau statement SQL SETELAH satu mutasi Mongo dalam operasi
     * yang sama gagal (create(): produk kedua kehabisan stok setelah
     * produk pertama/varian sukses dikurangi; restoreStock(): baris
     * setelah satu varian sudah dikembalikan gagal), mutasi Mongo yang
     * sudah ter-apply tetap ada tanpa ini.
     *
     * $sign: +1 untuk membatalkan decrement (create() gagal, kembalikan
     * stok), -1 untuk membatalkan increment (restoreStock() gagal,
     * kurangi lagi). Best-effort, dicatat ke log kalau kompensasi sendiri
     * gagal -- TIDAK menutup crash proses PERSIS di antara mutasi Mongo
     * asli dan titik kompensasi ini; itu perlu durable outbox/idempotent
     * stock operation, di luar cakupan perbaikan ini.
     */
    private function compensateMongoStock(array $adjustments, int $sign): void
    {
        foreach ($adjustments as $adjustment) {
            try {
                $variant = ProductVariantMongo::find((string) $adjustment['variant_id']);
                if ($variant) {
                    $variant->stock += $sign * $adjustment['qty'];
                    $variant->save();
                    Log::warning('Kompensasi stok varian Mongo setelah rollback SQL', [
                        'variant_id' => $adjustment['variant_id'],
                        'qty' => $adjustment['qty'],
                        'sign' => $sign,
                        'stock_after' => $variant->stock,
                    ]);
                }
            } catch (\Throwable $compensationError) {
                Log::error('Gagal mengompensasi stok varian Mongo setelah rollback SQL -- perlu koreksi manual', [
                    'variant_id' => $adjustment['variant_id'],
                    'qty' => $adjustment['qty'],
                    'sign' => $sign,
                    'error' => $compensationError->getMessage(),
                ]);
            }
        }
    }

    /**
     * Public entry point untuk caller restoreStock() mengompensasi Mongo
     * setelah OUTER transaction mereka sendiri rollback -- lihat docblock
     * restoreStock() untuk kenapa restoreStock() sendiri tidak lagi boleh
     * jadi compensation boundary mandiri. $sign selalu -1 di sini (restoreStock
     * menaikkan stok varian; rollback harus menurunkannya lagi) supaya
     * caller tidak perlu tahu/salah pilih arah sign.
     */
    public function compensateStockRestoreRollback(array $mongoAdjustments): void
    {
        $this->compensateMongoStock($mongoAdjustments, sign: -1);
    }

    public function create(array $data)
    {
        return $this->checkout([$data], (bool) ($data['use_balance'] ?? false))->first();
    }

    /**
     * One order per store, all created together and paid with ONE Midtrans
     * payment. Each order stays a normal transaction (its own escrow, status,
     * refund); a multi-store checkout only adds a shared payment_code, the
     * order_id Midtrans knows them by. A single order keeps paying under its
     * own code, exactly as before.
     *
     * With $useBalance, Saldo Blukios pays first (see payWithBalance); when it
     * covers the whole payment the orders are paid here and Midtrans is never
     * called.
     *
     * @param  list<array<string, mixed>>  $orders
     * @return Collection<int, Transaction>
     */
    public function checkout(array $orders, bool $useBalance = false): Collection
    {
        DB::beginTransaction();

        // MongoDB (ProductVariantMongo) TIDAK ikut DB::beginTransaction()
        // di atas -- koneksi terpisah, tidak ada distributed transaction.
        // Kalau produk KEDUA dalam loop di bawah gagal (mis. insufficient
        // stock) setelah produk PERTAMA (varian) sudah mengurangi stok
        // Mongo-nya, DB::rollBack() di catch cuma membatalkan sisi Postgres --
        // decrement Mongo yang sudah ter-apply tetap ada selamanya tanpa
        // kompensasi ini. Dicatat di sini, dikembalikan manual di catch.
        $mongoAdjustments = [];
        $codes = [];
        $committed = false;

        try {
            // A loop, not collect()->map(fn ...): an arrow function captures
            // $mongoAdjustments by value, so the catch below would never see
            // what an earlier order took from Mongo stock.
            /** @var Collection<int, Transaction> $transactions */
            $transactions = new Collection;
            foreach ($orders as $data) {
                $transactions->push($this->createOrder($data, $mongoAdjustments, $codes));
            }

            if ($transactions->count() > 1) {
                $paymentCode = 'BLKP'.now()->format('dmYHis').mt_rand(100, 999);
                foreach ($transactions as $transaction) {
                    $transaction->payment_code = $paymentCode;
                    $transaction->save();
                }
            }

            $paidWithBalance = $useBalance && $this->payWithBalance($transactions);

            DB::commit();
            // From here an error must neither "undo" Mongo stock nor roll
            // back a transaction that is no longer ours.
            $committed = true;
            foreach ($transactions as $transaction) {
                BusinessMetrics::record('order_created');
            }

            if ($paidWithBalance) {
                foreach ($transactions as $transaction) {
                    BusinessMetrics::record('payment_paid', 'balance');
                    event(new TransactionStatusUpdated($transaction->fresh()));
                }
            } else {
                // One token for the whole payment (the gateway sums the group).
                $first = $transactions->firstOrFail()->load('buyer.user');
                $snapToken = $this->paymentGateway->getSnapToken($first);
                if ($snapToken === null) {
                    // Unpayable orders would hold stock and Saldo Blukios until
                    // the expiry job; give both back now.
                    $this->abandonPayment($transactions);

                    throw new Exception($transactions->contains(fn (Transaction $t) => Money::fromDecimalString((string) ($t->balance_used ?? 0))->greaterThan(Money::zero()))
                        ? 'Pembayaran gagal dibuat; Saldo Blukios sudah dikembalikan. Silakan coba lagi.'
                        : 'Pembayaran gagal dibuat. Silakan coba lagi.', 502);
                }
                foreach ($transactions as $transaction) {
                    $transaction->snap_token = $snapToken;
                    $transaction->save();
                }
            }

            Log::info('=== CHECKOUT COMPLETED ===', ['orders' => $transactions->count()]);

            return $transactions->map(fn (Transaction $t) => $t->fresh(['buyer', 'store', 'transactionDetails.product']));

        } catch (\Throwable $e) {
            // Kompensasi Mongo SEBELUM rollback SQL, bukan sesudah --
            // DB::rollBack() melepas row lock Product yang dipegang di loop
            // atas, dan lock itu satu-satunya penjamin serialisasi mutasi
            // Mongo (lihat komentar di loop). Kalau rollback jalan dulu,
            // transaksi lain bisa mengunci Product, membaca stok Mongo yang
            // masih "salah" (belum dikompensasi), dan mutasinya sendiri bisa
            // ketiban timpa oleh compensateMongoStock() di bawah -- lost
            // update, karena mutasi Mongo di sini read -> modify -> save(),
            // bukan atomic increment.
            if (! $committed) {
                $this->compensateMongoStock($mongoAdjustments, sign: 1); // decrement gagal -> kembalikan (+)
                DB::rollBack();
            }
            $errorMsg = 'REPO FATAL ERROR: '.$e->getMessage()."\n".$e->getTraceAsString();
            Log::error($errorMsg);
            // file_put_contents(storage_path('logs/debug.txt'), $errorMsg, FILE_APPEND); // Reverted original or comment out
            throw new Exception($e->getMessage(), (int) $e->getCode());
        }
    }

    /**
     * Saldo Blukios pays the orders in creation order until it runs out; true
     * when it covered the whole payment, whose orders are then paid here.
     *
     * Runs inside checkout()'s transaction after every order exists. Lock
     * order everywhere: transactions -> products -> seller wallet -> buyer.
     * The sellers' wallets are taken before the buyer row, because a balance
     * that covers everything credits escrow below; the buyer row, read
     * locked, is the last lock, so concurrent checkouts queue on it and the
     * second sees what the first left.
     *
     * @param  Collection<int, Transaction>  $transactions
     */
    private function payWithBalance(Collection $transactions): bool
    {
        // ponytail: wallets are locked even when the balance only covers part;
        // held only until this checkout commits.
        StoreBalance::whereIn('store_id', $transactions->pluck('store_id'))->orderBy('id')->lockForUpdate()->get();
        $left = Money::fromDecimalString($this->buyerBalanceRepository->lockedBalance($transactions->firstOrFail()->buyer_id));

        $used = [];
        foreach ($transactions as $transaction) {
            if (! $left->greaterThan(Money::zero())) {
                break;
            }
            $total = Money::fromDecimalString((string) $transaction->grand_total);
            $use = $left->lessThan($total) ? $left : $total;
            if ($use->isZero()) {
                continue;
            }

            $transaction->balance_used = (string) $use->minor();
            $transaction->save();
            $used[] = [$transaction, $use];
            $left = $left->subtract($use);
        }

        $paid = $used !== [] && Transaction::midtransTotal($transactions)->isZero();
        if ($paid) {
            foreach ($transactions as $transaction) {
                $this->markPaid($transaction);
            }
        }

        // Last: the buyer row lock (see BuyerBalanceRepositoryInterface).
        foreach ($used as [$transaction, $use]) {
            $this->buyerBalanceRepository->debit(
                $transaction->buyer_id,
                (string) $use->minor(),
                BuyerBalanceHistory::TYPE_PAYMENT,
                'payment:'.$transaction->id,
                $transaction,
                'Pembayaran pesanan '.$transaction->code,
            );
        }

        return $paid;
    }

    /**
     * Fails the still-unpaid orders of a committed checkout whose payment
     * could not be created, returning their stock and Saldo Blukios.
     *
     * @param  Collection<int, Transaction>  $transactions
     */
    private function abandonPayment(Collection $transactions): void
    {
        Log::error('Snap token gagal dibuat, pesanan dibatalkan', [
            'orders' => $transactions->pluck('code')->all(),
        ]);

        $mongoAdjustments = [];
        DB::beginTransaction();

        try {
            $unpaid = Transaction::whereIn('id', $transactions->pluck('id'))->orderBy('id')->lockForUpdate()->get()
                ->filter(fn (Transaction $t) => in_array($t->payment_status, ['pending', 'unpaid'], true));
            $this->failPayment($unpaid, $mongoAdjustments);

            DB::commit();
        } catch (\Throwable $e) {
            // Kompensasi SEBELUM rollback -- lihat docblock restoreStock().
            $this->compensateStockRestoreRollback($mongoAdjustments);
            DB::rollBack();

            throw $e;
        }

        foreach ($unpaid as $transaction) {
            event(new TransactionStatusUpdated($transaction->fresh()));
        }
    }

    public function markPaid(Transaction $transaction): void
    {
        $transaction->payment_status = 'paid';
        $transaction->save();

        $this->escrowRepository->credit($transaction);
    }

    public function markFailed(Transaction $transaction, array &$mongoAdjustments): void
    {
        $this->failPayment([$transaction], $mongoAdjustments);
    }

    public function failPayment(iterable $transactions, array &$mongoAdjustments): void
    {
        // Lock order everywhere: transactions -> products -> seller wallet ->
        // buyer. So every order's stock first, then every balance return.
        foreach ($transactions as $transaction) {
            $transaction->payment_status = 'failed';
            $transaction->save();

            $this->restoreStock($transaction, $mongoAdjustments);
        }

        // unique_ref makes a repeat a no-op.
        foreach ($transactions as $transaction) {
            $balanceUsed = Money::fromDecimalString((string) ($transaction->balance_used ?? 0));
            if ($balanceUsed->greaterThan(Money::zero())) {
                $this->buyerBalanceRepository->credit(
                    $transaction->buyer_id,
                    (string) $balanceUsed->minor(),
                    BuyerBalanceHistory::TYPE_PAYMENT_RETURNED,
                    'payment_returned:'.$transaction->id,
                    $transaction,
                    'Pesanan '.$transaction->code.' batal dibayar',
                );
            }
        }
    }

    public function refundLatePayment(Transaction $transaction): bool
    {
        $amount = $transaction->midtransAmount();
        // Saldo Blukios paid all of it and was already returned: nothing owed.
        if ($transaction->refund_status !== null || $amount->isZero()) {
            return false;
        }

        $transaction->refund_status = 'processing';
        $transaction->refund_amount = (string) $amount->minor();
        $transaction->refund_reason = 'Pembayaran masuk setelah pesanan kedaluwarsa';
        $transaction->save();

        Log::warning('Pembayaran masuk untuk pesanan yang sudah gagal, direfund', [
            'transaction' => $transaction->code,
            'refund_amount' => $amount->minor(),
        ]);

        return true;
    }

    public function startRefund(Transaction $transaction): void
    {
        BusinessMetrics::record('refund_requested');

        try {
            RefundCancelledTransactionJob::dispatch($transaction->id);
        } catch (\Throwable $e) {
            // Pembatalan sudah commit; jangan biarkan refund menggantung di
            // "processing" tanpa job yang akan menyelesaikannya.
            Log::error('Refund job gagal didispatch, dialihkan ke Saldo Blukios', [
                'transaction' => $transaction->code,
                'error' => $e->getMessage(),
            ]);

            try {
                $this->refundToBalance($transaction->id, 'Refund otomatis tidak bisa dijadwalkan: '.mb_substr($e->getMessage(), 0, 500));
            } catch (\Throwable $balanceError) {
                // Tetap "processing": ops:check melaporkan refund yang macet.
                Log::error('Refund ke Saldo Blukios gagal', [
                    'transaction' => $transaction->code,
                    'error' => $balanceError->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $mongoAdjustments
     * @param  list<string>  $codes
     */
    private function createOrder(array $data, array &$mongoAdjustments, array &$codes): Transaction
    {
        Log::info('=== START CREATE TRANSACTION ===');
        Log::info('Input data:', ['data' => $data]);

        // buyer_id dan store_id dulu diambil apa adanya dari payload, jadi
        // pembeli yang sudah login bisa memesan atas nama buyer lain dan
        // menempelkan pesanan ke toko mana pun. Keduanya sekarang
        // diturunkan di server, dan $data ditimpa supaya seluruh jalur
        // hilir -- validasi voucher, detail transaksi -- ikut memakai
        // nilai yang sudah tepercaya, bukan kiriman client.
        $data['buyer_id'] = $this->resolveBuyerId();
        $data['store_id'] = $this->resolveStoreId($data['products']);
        $data['shipping_cost'] = $this->resolveShippingCost($data);

        $transaction = new Transaction;

        // Several orders are created in the same second; keep their codes apart.
        do {
            $code = 'BLK'.now()->format('dmYHis').mt_rand(10, 99);
        } while (in_array($code, $codes, true));
        $codes[] = $code;
        $transaction->code = $code;
        $transaction->buyer_id = $data['buyer_id'];
        $transaction->store_id = $data['store_id'];
        $transaction->address_id = $data['address_id'];
        $transaction->address = $data['address'];
        $transaction->city = $data['city'];
        $transaction->postal_code = $data['postal_code'];
        $transaction->dest_latitude = $data['dest_latitude'] ?? null;
        $transaction->dest_longitude = $data['dest_longitude'] ?? null;
        $transaction->shipping = $data['shipping'];
        $transaction->shipping_type = $data['shipping_type'];

        $transaction->shipping_cost = $data['shipping_cost'];
        $transaction->tax = 0;
        $transaction->grand_total = 0;
        $transaction->save();

        Log::info('Transaction created:', ['transaction_id' => $transaction->id]);

        $transactionDetailRepository = new TransactionDetailRepository;
        $transactionDetails = [];

        foreach ($data['products'] as $productData) {
            // Find Product with Lock for Atomic Update
            Log::debug('REPO: Deduction loop for Product ID: '.$productData['product_id']);

            $product = Product::where('id', $productData['product_id'])->lockForUpdate()->first();

            if (! $product) {
                Log::error('REPO: Product NOT FOUND ID: '.$productData['product_id']);
                throw new Exception('Product not found: '.$productData['product_id']);
            }

            Log::debug("REPO: Found Prod {$product->id} | Stock: {$product->stock} | Qty: {$productData['qty']}");

            if ($product->stock < $productData['qty']) {
                Log::error("REPO ERROR: Insufficient stock for {$product->id}. Has {$product->stock}, need {$productData['qty']}");
                throw new Exception('Insufficient stock for product: '.$product->name);
            }

            // products.price adalah harga varian TERMURAH (lihat
            // ProductRepository::create/update -- collect($variants)->min('price')),
            // bukan harga produk yang sesungguhnya kalau produk ini
            // punya varian. variant_id sebelumnya sama sekali tidak
            // sampai ke sini (payload cuma product_id+qty), jadi
            // pembelian varian mana pun selalu ditagih harga varian
            // termurah, dan stok yang berkurang cuma agregat produk --
            // stok varian spesifik (Mongo) tidak pernah tersentuh, jadi
            // varian yang sudah habis tetap bisa "dibeli" selama
            // agregat produk masih > 0. resolveVariant() di bawah
            // menutup keduanya sekaligus.
            $variant = $this->resolveVariant($product, $productData['variant_id'] ?? null);
            // Raw decimal string, not (float): TransactionDetailRepository
            // parses it with Money::fromDecimalString() (B3.1). Casting
            // through float here would both re-admit imprecision and
            // hide a fractional-rupiah price behind "10500.5".
            $unitPrice = (string) ($variant ? $variant->price : $product->price);

            if ($variant) {
                if ($variant->stock < $productData['qty']) {
                    throw new Exception("Stok varian tidak cukup untuk produk: {$product->name}");
                }
                $variant->stock -= $productData['qty'];
                $variant->save();
                $mongoAdjustments[] = ['variant_id' => $variant->id, 'qty' => $productData['qty']];
            }

            // Agregat products.stock tetap dikurangi seperti sebelumnya
            // (dashboard/listing lain bergantung padanya sebagai total
            // lintas varian) -- lock Postgres pada baris Product di atas
            // yang jadi satu-satunya penjamin serialisasi juga untuk
            // mutasi stok varian di Mongo, karena MongoDB sendiri tidak
            // punya SELECT ... FOR UPDATE. Dua pembeli yang bersamaan
            // checkout varian BEDA dari produk yang SAMA tetap serial
            // lewat lock Product ini, bukan lewat Mongo.
            $oldStock = $product->stock;
            $product->stock -= $productData['qty'];
            $product->save();

            Log::debug("REPO: Updated Stock {$oldStock} -> {$product->stock}");

            $detail = $transactionDetailRepository->create([
                'transaction_id' => $transaction->id,
                'product_id' => $productData['product_id'],
                'variant_id' => $variant?->id,
                'qty' => $productData['qty'],
                'unit_price' => $unitPrice,
            ]);

            $detail->load('product');
            $transactionDetails[] = $detail;
        }

        Log::info('Transaction details created:', ['count' => count($transactionDetails)]);

        // Subtotal stays a Money value object all the way to the
        // persistence boundary (B3.2b). detail->subtotal is Money
        // (B3.1 pilot); tax is the only rounded step.
        $subtotal = array_reduce(
            $transactionDetails,
            fn (Money $carry, $item) => $carry->add($item->subtotal),
            Money::zero()
        );

        Log::info('Subtotal calculated:', ['subtotal' => $subtotal->minor()]);

        // No tax on the buyer: goods VAT is the seller's obligation (only
        // PKP sellers charge it, inside their own price), not a marketplace
        // surcharge. The column stays for older transactions that had it.
        $tax = Money::zero();

        // shipping_cost was resolved server-side as whole rupiah.
        $grandTotal = $subtotal
            ->add(Money::rupiah((int) $data['shipping_cost']));

        // Voucher: re-validate server-side against the SAME rules as
        // VoucherController::validateCode (Voucher::validateFor) — never
        // trust a client-supplied discount amount. A validate-then-checkout
        // race (e.g. usage_limit exhausted in between) is closed here
        // because this whole method runs inside one DB transaction.
        $discount = Money::zero();
        $voucher = null;
        if (! empty($data['voucher_code'])) {
            $voucher = Voucher::where('code', $data['voucher_code'])->first();
            if ($voucher) {
                $result = $voucher->validateFor($data['buyer_id'], $data['store_id'], $subtotal);
                if ($result['valid']) {
                    $discount = $result['discount_amount'];
                } else {
                    Log::warning('Voucher no longer valid at checkout time, ignoring:', [
                        'code' => $data['voucher_code'],
                        'reason' => $result['message'],
                    ]);
                    $voucher = null;
                }
            }
        }

        $grandTotal = $grandTotal->subtract($discount)->clampMin(Money::zero());

        // Added after the discount so a voucher never reduces it.
        $serviceFee = Money::rupiah((int) config('marketplace.buyer_service_fee'));
        $grandTotal = $grandTotal->add($serviceFee);

        $transaction->tax = $tax->minor();
        $transaction->service_fee = $serviceFee->minor();
        $transaction->grand_total = $grandTotal->minor();
        $transaction->voucher_id = $voucher?->id;
        $transaction->discount_amount = $discount->minor();
        $transaction->save();

        if ($voucher) {
            VoucherRedemption::create([
                'voucher_id' => $voucher->id,
                'buyer_id' => $data['buyer_id'],
                'transaction_id' => $transaction->id,
                'redeemed_at' => now(),
            ]);
        }

        Log::info('Transaction updated with costs:', [
            'subtotal' => $subtotal->minor(),
            'shipping_cost' => $transaction->shipping_cost,
            'tax' => $tax->minor(),
            'discount_amount' => $discount->minor(),
            'grand_total' => $grandTotal->minor(),
        ]);

        return $transaction;
    }

    public function delete(string $id)
    {
        DB::beginTransaction();
        $mongoAdjustments = [];

        try {
            $transaction = Transaction::where('id', $id)->lockForUpdate()->firstOrFail();

            // Its siblings would still be charged the whole payment's amount.
            if ($transaction->payment_code !== null) {
                throw new Exception('Pesanan dari pembayaran gabungan tidak bisa dihapus satu per satu', 422);
            }

            // An unpaid order still holds stock and any Saldo Blukios it used.
            if (in_array($transaction->payment_status, ['pending', 'unpaid'])) {
                $this->markFailed($transaction, $mongoAdjustments);
            }

            $transaction->delete();

            DB::commit();

            return $transaction;
        } catch (\Throwable $e) {
            // Kompensasi SEBELUM rollback -- lihat docblock restoreStock().
            $this->compensateStockRestoreRollback($mongoAdjustments);
            DB::rollBack();

            throw new Exception($e->getMessage(), (int) $e->getCode());
        }
    }

    /**
     * Mengembalikan stok satu transaksi. Idempoten dan mengunci dirinya
     * sendiri, supaya aman dipanggil dari jalur mana pun (webhook,
     * checkPaymentStatus manual, cancelPaidOrder saat seller membatalkan,
     * scheduler transaction:check-expiry) tanpa bergantung pada caller
     * mengingat untuk mengunci lebih dulu.
     *
     * Sebelumnya lockForUpdate() hanya pada baris Product -- benar untuk
     * aritmatika +=, tapi tidak mencegah transaksi yang SAMA di-restore
     * dua kali oleh dua caller berbeda yang kebetulan tumpang tindih.
     *
     * PENTING soal Mongo compensation: method ini TIDAK LAGI jadi
     * compensation boundary mandiri untuk mutasi stok varian yang
     * dilakukannya. Semua caller (delete(), cancelPaidOrder(), webhook
     * Midtrans, checkPaymentStatus, scheduler transaction:check-expiry)
     * memanggil ini dari DALAM transaksi SQL milik mereka sendiri yang
     * masih punya operasi lain SETELAHNYA (refund escrow, save status,
     * dst). Kalau restoreStock() sendiri yang mengompensasi begitu method
     * ini kembali sukses, lalu OPERASI SETELAHNYA di caller gagal dan
     * OUTER transaction rollback, mutasi Mongo yang sudah dikompensasi
     * (atau belum -- tergantung timing) jadi tidak sinkron dengan SQL
     * yang barusan di-rollback: stok varian Mongo tetap ter-restore
     * permanen padahal seluruh transaksi termasuk stock_restored_at-nya
     * batal.
     *
     * Kontrak baru: caller WAJIB menyediakan array $mongoAdjustments
     * (passed by reference, dipakai untuk logging/kompensasi), dan WAJIB
     * memanggil compensateStockRestoreRollback($mongoAdjustments) di blok
     * catch mereka sendiri SEBELUM DB::rollBack() -- BUKAN sesudah. Row
     * lock Product yang diambil restoreStock() ini (lockForUpdate() di
     * loop di bawah) satu-satunya penjamin serialisasi mutasi stok
     * varian Mongo (Mongo sendiri tidak punya SELECT ... FOR UPDATE);
     * lock itu baru benar-benar lepas saat SQL rollback/commit terjadi
     * di caller, TIDAK oleh commit transaksi bersarang milik
     * restoreStock() ini sendiri. Kalau DB::rollBack() dijalankan lebih
     * dulu, lock terlepas SEBELUM kompensasi -- caller lain bisa
     * mengunci Product itu di antaranya, membaca stok Mongo yang masih
     * "salah" (belum dikompensasi), lalu mutasinya sendiri tertimpa
     * balik oleh compensateMongoStock() yang jalan belakangan (lost
     * update, karena mutasi Mongo di sini read -> modify -> save(),
     * bukan atomic increment) -- lihat cancelPaidOrder() untuk contoh
     * urutan yang benar. restoreStock() sendiri tidak menangkap
     * exception apa pun lagi; propagate apa adanya ke caller, yang
     * memang sudah dalam try/catch mereka masing-masing.
     */
    public function restoreStock(Transaction $transaction, array &$mongoAdjustments): void
    {
        DB::transaction(function () use ($transaction, &$mongoAdjustments) {
            $locked = Transaction::where('id', $transaction->id)->lockForUpdate()->first();

            if (! $locked || $locked->stock_restored_at !== null) {
                return;
            }

            $locked->load('transactionDetails');

            foreach ($locked->transactionDetails as $detail) {
                $product = Product::where('id', $detail->product_id)->lockForUpdate()->first();
                if ($product) {
                    $product->stock += $detail->qty;
                    $product->save();
                    Log::info("Product {$product->id} RESTORED: Stock -> {$product->stock}");
                }

                // Simetris dengan pengurangan stok varian di create() --
                // tanpa ini, pembatalan/expiry transaksi yang membeli
                // varian tertentu cuma mengembalikan agregat produk,
                // stok varian spesifiknya tetap hilang permanen.
                if ($detail->variant_id) {
                    $variant = ProductVariantMongo::find((string) $detail->variant_id);
                    if ($variant) {
                        $variant->stock += $detail->qty;
                        $variant->save();
                        $mongoAdjustments[] = ['variant_id' => $variant->id, 'qty' => $detail->qty];
                        Log::info("Variant {$variant->id} RESTORED: Stock -> {$variant->stock}");
                    }
                }
            }

            $locked->stock_restored_at = now();
            $locked->save();

            // Sinkronkan instance yang dipegang caller supaya perubahan
            // yang mereka simpan setelah pemanggilan ini (mis. payment_status)
            // tidak menimpa balik stock_restored_at dengan versi lama di memori.
            $transaction->stock_restored_at = $locked->stock_restored_at;
        });
    }

    public function updateStatus(string $id, array $data)
    {
        // Pembatalan wajib lewat cancelPaidOrder(): ia juga mengembalikan
        // uang pembeli (refund_status + RefundCancelledTransactionJob), bukan
        // cuma stok dan saldo tertahan penjual.
        if (isset($data['delivery_status']) && ! in_array($data['delivery_status'], ['processing', 'delivering'], true)) {
            throw new Exception('Gunakan pembatalan pesanan untuk membatalkan', 422);
        }

        DB::beginTransaction();

        try {
            // Lock: seller bisa mengirim update shipping dua kali nyaris
            // bersamaan (double-klik, retry jaringan) -- harus serial per
            // transaksi, bukan berdasar baca tanpa kunci.
            $transaction = Transaction::where('id', $id)->lockForUpdate()->first();

            if (! $transaction) {
                throw new Exception('Data Transaksi Tidak Ditemukan', 404);
            }

            if (Complaint::activeFor($transaction->id)) {
                throw new Exception('Pesanan sedang dikomplain', 422);
            }

            if (isset($data['tracking_number'])) {
                $transaction->tracking_number = $data['tracking_number'];
            }

            if (isset($data['delivery_proof']) && $data['delivery_proof'] instanceof UploadedFile) {
                $transaction->delivery_proof = $data['delivery_proof']->store('assets/transaction', 'public');
            }

            if (isset($data['delivery_status'])) {
                $transaction->delivery_status = $data['delivery_status'];
            }

            $transaction->save();

            DB::commit();

            return $transaction->fresh([
                'buyer.user',
                'store.user',
                'transactionDetails.product',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            throw new Exception($e->getMessage(), (int) $e->getCode());
        }
    }

    /**
     * Selesaikan pesanan dan rilis escrow dalam SATU transaksi terkunci.
     *
     * Sebelumnya controller memanggil updateStatus() (commit sendiri) lalu
     * escrowRepository->release() sebagai operasi terpisah setelahnya, dan
     * scheduler transaction:auto-complete melakukan urutan yang sama tanpa
     * lock sama sekali. Kalau proses mati di antara keduanya, atau dua
     * caller (buyer klik selesai + scheduler auto-complete) tumpang tindih
     * pada transaksi yang sama, delivery_status bisa jadi "completed"
     * sementara dana belum pernah dirilis, atau dirilis dua kali.
     *
     * Dipakai oleh TransactionController::complete() (buyer) dan
     * AutoCompleteTransaction (scheduler harian) -- satu tempat, bukan dua
     * implementasi yang bisa diam-diam berbeda.
     */
    public function completeTransaction(string $id, ?string $receivingProof = null, bool $auto = false): Transaction
    {
        return DB::transaction(function () use ($id, $receivingProof, $auto) {
            $transaction = Transaction::where('id', $id)->lockForUpdate()->first();

            if (! $transaction) {
                throw new Exception('Data Transaksi Tidak Ditemukan', 404);
            }

            if ($transaction->delivery_status !== 'delivering') {
                throw new Exception('Hanya status delivering yang bisa diselesaikan', 400);
            }

            if (Complaint::activeFor($transaction->id)) {
                throw new Exception('Tarik komplain terlebih dulu sebelum menyelesaikan pesanan', 422);
            }

            // A complaint closed without refund gives the buyer 2 more days
            // before the scheduler completes the order.
            if ($auto && Complaint::where('transaction_id', $transaction->id)->where('resolved_at', '>', now()->subDays(2))->exists()) {
                throw new Exception('Komplain baru selesai, pesanan belum diselesaikan otomatis', 422);
            }

            $transaction->delivery_status = 'completed';
            if ($receivingProof !== null) {
                $transaction->receiving_proof = $receivingProof;
            }
            $transaction->save();

            $this->escrowRepository->release($transaction);

            return $transaction->fresh([
                'buyer.user',
                'store.user',
                'transactionDetails.product',
            ]);
        });
    }

    /**
     * Penjual menolak pesanan yang sudah dibayar, sebelum dikirim.
     *
     * Dalam satu transaksi terkunci: stok kembali, saldo tertahan penjual
     * ditarik (EscrowRepository::refund), pesanan jadi cancelled, dan
     * refund_status = processing. payment_status ikut jadi failed seperti
     * pembatalan lain, supaya analitik omzet (payment_status = paid) tidak
     * menghitungnya; nasib uang pembeli dicatat di kolom refund_*.
     * Bagian Saldo Blukios (balance_used) langsung kembali di sini;
     * refund_amount hanya bagian Midtrans. Kalau itu 0, refund selesai di sini.
     *
     * Uang pembeli dikembalikan oleh RefundCancelledTransactionJob SETELAH
     * commit: panggilan ke Midtrans tidak boleh terjadi untuk pembatalan yang
     * ternyata di-rollback.
     */
    public function cancelPaidOrder(string $id, string $reason): Transaction
    {
        DB::beginTransaction();
        $mongoAdjustments = [];

        try {
            $transaction = Transaction::where('id', $id)->lockForUpdate()->first();

            if (! $transaction) {
                throw new Exception('Data Transaksi Tidak Ditemukan', 404);
            }

            $this->refundPaidOrder($transaction, $reason, ['pending', 'processing'], true, $mongoAdjustments);

            DB::commit();
        } catch (\Throwable $e) {
            // Kompensasi SEBELUM rollback -- lihat docblock restoreStock().
            $this->compensateStockRestoreRollback($mongoAdjustments);
            DB::rollBack();

            throw $e;
        }

        $this->startRefundAfterCommit($transaction);

        return $transaction->fresh([
            'buyer.user',
            'store.user',
            'transactionDetails.product',
        ]);
    }

    /**
     * Full refund of a paid order, shared by the seller's cancel and an
     * approved complaint: escrow leaves the seller's pending balance,
     * balance_used goes back to Saldo Blukios now, refund_amount is the
     * Midtrans part (0 = refunded right here). Caller holds the order's lock
     * and its DB transaction, then calls startRefundAfterCommit().
     *
     * @param  list<string>  $deliveryStates  states the order may be in
     * @param  bool  $restock  false when the buyer keeps the goods (complaint)
     */
    private function refundPaidOrder(Transaction $transaction, string $reason, array $deliveryStates, bool $restock, array &$mongoAdjustments): void
    {
        if ($transaction->payment_status !== 'paid') {
            throw new Exception('Hanya pesanan yang sudah dibayar yang bisa direfund', 422);
        }

        if (! in_array($transaction->delivery_status, $deliveryStates, true)) {
            throw new Exception(in_array($transaction->delivery_status, ['delivering', 'completed'], true)
                ? 'Pesanan yang sudah dikirim tidak bisa dibatalkan'
                : 'Status pesanan sudah berubah, refund tidak bisa diproses', 422);
        }

        if ($restock) {
            $this->restoreStock($transaction, $mongoAdjustments);
        }
        $this->escrowRepository->refund($transaction);

        // The Saldo Blukios part goes back now; only the Midtrans part
        // (refund_amount) is left for the job.
        $balanceUsed = Money::fromDecimalString((string) ($transaction->balance_used ?? 0));
        if ($balanceUsed->greaterThan(Money::zero())) {
            $this->buyerBalanceRepository->credit(
                $transaction->buyer_id,
                (string) $balanceUsed->minor(),
                BuyerBalanceHistory::TYPE_REFUND,
                'refund_balance:'.$transaction->id,
                $transaction,
                'Refund pesanan '.$transaction->code,
            );
        }

        $refundAmount = $transaction->midtransAmount();
        $transaction->delivery_status = 'cancelled';
        $transaction->payment_status = 'failed';
        $transaction->refund_amount = (string) $refundAmount->minor();
        $transaction->refund_reason = $reason;
        if ($refundAmount->isZero()) {
            $transaction->refund_status = 'refunded';
            $transaction->refund_method = 'balance';
            $transaction->refunded_at = now();
        } else {
            $transaction->refund_status = 'processing';
        }
        $transaction->save();
    }

    private function startRefundAfterCommit(Transaction $transaction): void
    {
        if ($transaction->refund_status === 'refunded') {
            BusinessMetrics::record('refund_done', 'balance');
        } else {
            $this->startRefund($transaction);
        }
    }

    /**
     * Pembeli mengajukan komplain untuk pesanan yang sedang dikirim. Pesanan
     * dikunci dulu supaya tidak beririsan dengan completeTransaction().
     *
     * @param  array{reason: string, description: string}  $data
     * @param  list<UploadedFile>  $photos
     */
    public function createComplaint(string $transactionId, array $data, array $photos): Transaction
    {
        // Stored before the lock (no file I/O under it) and deleted if the insert fails.
        $paths = array_map(fn (UploadedFile $photo) => $photo->store('assets/complaint', 'public'), $photos);

        try {
            $transaction = DB::transaction(fn () => $this->insertComplaint($transactionId, $data, $paths));
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($paths);

            throw $e;
        }

        ComplaintAlerts::send($transaction);

        return $transaction;
    }

    /**
     * @param  array{reason: string, description: string}  $data
     * @param  list<string>  $paths
     */
    private function insertComplaint(string $transactionId, array $data, array $paths): Transaction
    {
        $transaction = Transaction::where('id', $transactionId)->lockForUpdate()->first();

        if (! $transaction) {
            throw new Exception('Data Transaksi Tidak Ditemukan', 404);
        }

        if ($transaction->payment_status !== 'paid' || $transaction->delivery_status !== 'delivering') {
            throw new Exception('Komplain hanya bisa diajukan untuk pesanan yang sedang dikirim', 422);
        }

        if (Complaint::where('transaction_id', $transaction->id)->exists()) {
            throw new Exception('Pesanan ini sudah pernah dikomplain', 422);
        }

        Complaint::create([
            'transaction_id' => $transaction->id,
            'reason' => $data['reason'],
            'description' => $data['description'],
            'photos' => $paths,
            'status' => 'open',
            'deadline_at' => now()->addDays(2),
        ]);

        return $transaction->fresh(['buyer.user', 'store.user', 'transactionDetails.product', 'complaint']);
    }

    /**
     * Pindahkan komplain dari salah satu status $from ke $to. Kunci selalu
     * pesanan dulu lalu komplain (urutan yang sama dengan completeTransaction).
     * approved = refund penuh dalam transaksi DB yang sama, tanpa
     * mengembalikan stok (barang ada di pembeli).
     *
     * @param  list<string>  $from
     * @param  array<string, mixed>  $changes  seller_response, admin_note, resolved_by
     */
    public function moveComplaint(string $complaintId, array $from, string $to, array $changes = []): Transaction
    {
        $transactionId = Complaint::where('id', $complaintId)->value('transaction_id');
        if (! $transactionId) {
            throw new Exception('Komplain tidak ditemukan', 404);
        }

        $transaction = DB::transaction(function () use ($transactionId, $complaintId, $from, $to, $changes) {
            $transaction = Transaction::where('id', $transactionId)->lockForUpdate()->firstOrFail();
            $complaint = Complaint::where('id', $complaintId)->lockForUpdate()->firstOrFail();

            if (! in_array($complaint->status, $from, true)) {
                throw new Exception('Status komplain sudah berubah', 422);
            }

            if ($to === 'approved') {
                $noStock = [];
                $this->refundPaidOrder($transaction, 'Komplain pembeli disetujui', ['delivering'], false, $noStock);
            }

            $complaint->fill($changes);
            $complaint->status = $to;
            if ($to === 'escalated') {
                $complaint->escalated_at = now();
            } else {
                $complaint->resolved_at = now();
            }
            $complaint->save();

            return $transaction;
        });

        $transaction = $transaction->fresh(['buyer.user', 'store.user', 'transactionDetails.product', 'complaint']);

        // Only an approval changes the order itself (cancelled + refund). The
        // change is committed: a failure here is logged, never reported.
        if ($to === 'approved') {
            try {
                $this->startRefundAfterCommit($transaction);
                event(new TransactionStatusUpdated($transaction));
            } catch (\Throwable $e) {
                Log::error('Langkah setelah komplain disetujui gagal', ['complaint' => $complaintId, 'error' => $e->getMessage()]);
            }
        }

        ComplaintAlerts::send($transaction);

        return $transaction;
    }

    /**
     * Pembeli mengisi rekening tujuan untuk refund manual. Boleh diganti
     * selama platform belum mentransfer.
     *
     * @param  array{refund_bank_name: string, refund_account_number: string, refund_account_name: string}  $account
     */
    public function saveRefundAccount(string $id, array $account): Transaction
    {
        return DB::transaction(function () use ($id, $account) {
            $transaction = Transaction::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($transaction->refund_status !== 'manual_required') {
                throw new Exception('Pesanan ini tidak membutuhkan rekening refund', 422);
            }

            $transaction->fill($account)->save();

            return $transaction->fresh(['buyer.user', 'store.user', 'transactionDetails.product']);
        });
    }

    /**
     * Admin mencatat bahwa refund manual sudah ditransfer ke pembeli.
     */
    public function markRefundTransferred(string $id, string $note): Transaction
    {
        return DB::transaction(function () use ($id, $note) {
            $transaction = Transaction::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($transaction->refund_status !== 'manual_required') {
                throw new Exception('Pesanan ini tidak sedang menunggu refund manual', 422);
            }

            if (! $transaction->refund_account_number) {
                throw new Exception('Pembeli belum mengisi rekening refund', 422);
            }

            $transaction->refund_status = 'refunded';
            $transaction->refund_method = 'manual';
            $transaction->refund_note = $note;
            $transaction->refunded_at = now();
            $transaction->save();

            BusinessMetrics::record('refund_done', 'manual');

            return $transaction->fresh(['buyer.user', 'store.user', 'transactionDetails.product']);
        });
    }

    /**
     * Mengembalikan refund_amount ke Saldo Blukios pembeli. Dipakai untuk
     * semua refund yang tidak bisa lewat API Midtrans, dan oleh admin untuk
     * pesanan lama yang masih manual_required.
     *
     * Null (tanpa perubahan) bila refund_status saat dikunci bukan $from,
     * misalnya sudah refunded lewat webhook Midtrans. Pemanggil yang
     * menyiarkan TransactionStatusUpdated.
     */
    public function refundToBalance(string $id, string $note, string $from = 'processing'): ?Transaction
    {
        $transaction = DB::transaction(function () use ($id, $note, $from) {
            $transaction = Transaction::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($transaction->refund_status !== $from) {
                return null;
            }

            $this->buyerBalanceRepository->credit(
                $transaction->buyer_id,
                (string) $transaction->refund_amount,
                BuyerBalanceHistory::TYPE_REFUND,
                'refund:'.$transaction->id,
                $transaction,
                'Refund pesanan '.$transaction->code,
            );

            $transaction->refund_status = 'refunded';
            $transaction->refund_method = 'balance';
            $transaction->refund_note = $note;
            $transaction->refunded_at = now();
            $transaction->save();

            return $transaction;
        });

        if (! $transaction) {
            return null;
        }

        BusinessMetrics::record('refund_done', 'balance');
        Log::info('Refund dikembalikan ke Saldo Blukios', ['transaction' => $transaction->code]);

        return $transaction->fresh(['buyer.user', 'store.user', 'transactionDetails.product']);
    }
}
