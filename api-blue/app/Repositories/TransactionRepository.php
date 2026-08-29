<?php

namespace App\Repositories;

use App\Interfaces\EscrowRepositoryInterface;
use App\Interfaces\PaymentGatewayInterface;
use App\Interfaces\ShippingGatewayInterface;
use App\Interfaces\TransactionRepositoryInterface;
use App\Models\Product;
use App\Models\ProductVariantMongo;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionRepository implements TransactionRepositoryInterface
{
    public function __construct(
        private EscrowRepositoryInterface $escrowRepository,
        private PaymentGatewayInterface $paymentGateway,
        private ShippingGatewayInterface $shippingGateway
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
     * MySQL. Kalau statement SQL SETELAH satu mutasi Mongo dalam operasi
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
        DB::beginTransaction();

        // MongoDB (ProductVariantMongo) TIDAK ikut DB::beginTransaction()
        // di atas -- koneksi terpisah, tidak ada distributed transaction.
        // Kalau produk KEDUA dalam loop di bawah gagal (mis. insufficient
        // stock) setelah produk PERTAMA (varian) sudah mengurangi stok
        // Mongo-nya, DB::rollBack() di catch cuma membatalkan sisi MySQL --
        // decrement Mongo yang sudah ter-apply tetap ada selamanya tanpa
        // kompensasi ini. Dicatat di sini, dikembalikan manual di catch.
        $mongoAdjustments = [];

        try {
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

            $transaction->code = 'BLK'.now()->format('dmYHis').mt_rand(10, 99);
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
                $unitPrice = $variant ? (float) $variant->price : (float) $product->price;

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
                // lintas varian) -- lock MySQL pada baris Product di atas
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

            // ✅ Hitung subtotal dari produk saja
            $subtotal = array_reduce($transactionDetails, function ($carry, $item) {
                return $carry + $item->subtotal;
            }, 0);

            Log::info('Subtotal calculated:', ['subtotal' => $subtotal]);

            // ✅ Hitung tax dan grand total (TIDAK pakai API lagi)
            $tax = round($subtotal * 0.11);
            $grandTotal = round($subtotal + $tax + $transaction->shipping_cost);

            // Voucher: re-validate server-side against the SAME rules as
            // VoucherController::validateCode (Voucher::validateFor) — never
            // trust a client-supplied discount amount. A validate-then-checkout
            // race (e.g. usage_limit exhausted in between) is closed here
            // because this whole method runs inside one DB transaction.
            $discountAmount = 0;
            $voucher = null;
            if (! empty($data['voucher_code'])) {
                $voucher = Voucher::where('code', $data['voucher_code'])->first();
                if ($voucher) {
                    $result = $voucher->validateFor($data['buyer_id'], $data['store_id'], (float) $subtotal);
                    if ($result['valid']) {
                        $discountAmount = $result['discount_amount'];
                    } else {
                        Log::warning('Voucher no longer valid at checkout time, ignoring:', [
                            'code' => $data['voucher_code'],
                            'reason' => $result['message'],
                        ]);
                        $voucher = null;
                    }
                }
            }

            $grandTotal = max(0, round($grandTotal - $discountAmount));

            $transaction->tax = $tax;
            $transaction->grand_total = $grandTotal;
            $transaction->voucher_id = $voucher?->id;
            $transaction->discount_amount = $discountAmount;
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
                'subtotal' => $subtotal,
                'shipping_cost' => $transaction->shipping_cost,
                'tax' => $tax,
                'discount_amount' => $discountAmount,
                'grand_total' => $grandTotal,
            ]);

            DB::commit();

            Log::info('=== BEFORE MIDTRANS ===');

            // Load Buyer & User for Midtrans
            $transaction->load('buyer.user');

            // Transaksi sudah ter-commit; kegagalan gateway tidak boleh
            // membuat request 500 padahal order & stok sudah tercatat.
            // FE sudah menangani snap_token null dengan pesan yang jelas.
            $snapToken = $this->paymentGateway->getSnapToken($transaction);
            if ($snapToken !== null) {
                $transaction->snap_token = $snapToken;
                $transaction->save();
            }

            Log::info('=== TRANSACTION COMPLETED SUCCESSFULLY ===');

            return $transaction->fresh(['buyer', 'store', 'transactionDetails.product']);

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
            $this->compensateMongoStock($mongoAdjustments, sign: 1); // decrement gagal -> kembalikan (+)
            DB::rollBack();
            $errorMsg = 'REPO FATAL ERROR: '.$e->getMessage()."\n".$e->getTraceAsString();
            Log::error($errorMsg);
            // file_put_contents(storage_path('logs/debug.txt'), $errorMsg, FILE_APPEND); // Reverted original or comment out
            throw new Exception($e->getMessage());
        }
    }

    public function delete(string $id)
    {
        DB::beginTransaction();
        $mongoAdjustments = [];

        try {
            $transaction = Transaction::find($id);

            // Restore stock if transaction is being deleted and was holding stock (pending/unpaid)
            if (in_array($transaction->payment_status, ['pending', 'unpaid'])) {
                $this->restoreStock($transaction, $mongoAdjustments);
            }

            $transaction->delete();

            DB::commit();

            return $transaction;
        } catch (\Throwable $e) {
            // Kompensasi SEBELUM rollback -- lihat docblock restoreStock().
            $this->compensateStockRestoreRollback($mongoAdjustments);
            DB::rollBack();

            throw new Exception($e->getMessage());
        }
    }

    /**
     * Mengembalikan stok satu transaksi. Idempoten dan mengunci dirinya
     * sendiri, supaya aman dipanggil dari jalur mana pun (webhook,
     * checkPaymentStatus manual, updateStatus saat seller membatalkan,
     * scheduler transaction:check-expiry) tanpa bergantung pada caller
     * mengingat untuk mengunci lebih dulu.
     *
     * Sebelumnya lockForUpdate() hanya pada baris Product -- benar untuk
     * aritmatika +=, tapi tidak mencegah transaksi yang SAMA di-restore
     * dua kali oleh dua caller berbeda yang kebetulan tumpang tindih.
     *
     * PENTING soal Mongo compensation: method ini TIDAK LAGI jadi
     * compensation boundary mandiri untuk mutasi stok varian yang
     * dilakukannya. Semua caller (delete(), updateStatus(), webhook
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
     * bukan atomic increment) -- lihat updateStatus() untuk contoh
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
        DB::beginTransaction();
        // Dipakai kalau restoreStock() di bawah mengubah Mongo lalu operasi
        // SETELAHNYA di transaksi outer ini (refund escrow, save) gagal --
        // lihat docblock restoreStock() untuk kenapa restoreStock() sendiri
        // tidak lagi mengompensasi dirinya sendiri.
        $mongoAdjustments = [];

        try {
            // Lock: seller bisa mengirim update shipping dua kali nyaris
            // bersamaan (double-klik, retry jaringan), dan jalur cancel di
            // bawah memicu restoreStock + refund escrow -- keduanya harus
            // serial per transaksi, bukan berdasar baca tanpa kunci.
            $transaction = Transaction::where('id', $id)->lockForUpdate()->first();

            if (isset($data['tracking_number'])) {
                $transaction->tracking_number = $data['tracking_number'];
            }

            if (isset($data['delivery_proof']) && $data['delivery_proof'] instanceof UploadedFile) {
                $transaction->delivery_proof = $data['delivery_proof']->store('assets/transaction', 'public');
            }

            // Restore stock if being cancelled/failed AND it wasn't already cancelled/failed
            if (isset($data['delivery_status']) &&
                in_array($data['delivery_status'], ['cancelled', 'failed']) &&
                ! in_array($transaction->delivery_status, ['cancelled', 'failed'])) {

                $this->restoreStock($transaction, $mongoAdjustments);

                // Refund escrow: kembalikan pending_balance jika payment sudah paid
                if ($transaction->payment_status === 'paid') {
                    $this->escrowRepository->refund($transaction);
                }
            }

            if (isset($data['delivery_status'])) {
                $transaction->delivery_status = $data['delivery_status'];

                // Also sync payment status for consistency if cancelled
                if ($data['delivery_status'] === 'cancelled' && $transaction->payment_status !== 'failed') {
                    $transaction->payment_status = 'failed';
                }
            }

            $transaction->save();

            DB::commit();

            return $transaction->fresh([
                'buyer.user',
                'store.user',
                'transactionDetails.product',
            ]);
        } catch (\Throwable $e) {
            // Kompensasi SEBELUM rollback -- lihat docblock restoreStock().
            $this->compensateStockRestoreRollback($mongoAdjustments);
            DB::rollBack();

            throw new Exception($e->getMessage());
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
    public function completeTransaction(string $id, ?string $receivingProof = null): Transaction
    {
        return DB::transaction(function () use ($id, $receivingProof) {
            $transaction = Transaction::where('id', $id)->lockForUpdate()->first();

            if (! $transaction) {
                throw new Exception('Data Transaksi Tidak Ditemukan', 404);
            }

            if ($transaction->delivery_status !== 'delivering') {
                throw new Exception('Hanya status delivering yang bisa diselesaikan', 400);
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
}
