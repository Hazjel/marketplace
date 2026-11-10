# Batas waktu kirim + batal otomatis (rancangan)

Status: rancangan rev 3 (setelah review Codex 2 putaran), diimplementasi di
branch `feat/ship-deadline-auto-cancel`.
Tanggal: 2026-11-09.

## Masalah

Pesanan yang sudah dibayar (`payment_status = paid`) bisa diam di
`delivery_status = pending|processing` selamanya kalau penjual tidak mengirim.
Uang pembeli tertahan di escrow, stok tertahan, pembeli hanya bisa menunggu.
Tidak ada catatan kapan pesanan dibayar: `markPaid()` hanya mengubah
`payment_status` dan mengkredit escrow.

## Keputusan

1. **Satu tenggat**: pesanan harus sampai `delivering` (resi diinput) paling
   lambat `ship_deadline_days` hari kalender setelah dibayar. Default 3 hari,
   override env `SHIP_DEADLINE_DAYS` (dijepit minimal 1). `processing` (penjual
   menerima pesanan) tidak memperpanjang tenggat. Tidak ada hitungan hari
   kerja/libur di v1.
2. **Tenggat dibekukan saat bayar** (`ship_deadline_at`), bukan dihitung dari
   config setiap kali dibaca. Mengubah config tidak menggeser janji ke pesanan
   yang sudah jalan, dan scheduler cukup `WHERE ship_deadline_at <= now()`.
3. **Batal otomatis = `cancelPaidOrder()` yang sudah ada.** Ia sudah: kunci baris,
   validasi `paid` + `pending|processing` di dalam kunci, kembalikan stok, tarik
   escrow, kembalikan `balance_used` ke Saldo Blukios, set `refund_*`, lalu
   `RefundCancelledTransactionJob` setelah commit (fallback ke Saldo kalau
   dispatch gagal). Tidak ada jalur refund baru.
4. **Penjual menang sampai scheduler memproses pesanannya.** Kalau penjual input
   resi setelah tenggat tapi sebelum pesanan itu diproses, pesanan tetap jalan
   (barang sudah dikirim; membatalkan lebih merugikan pembeli). Scheduler jalan
   tiap 15 menit; itu irama, bukan jaminan (antrean panjang atau kegagalan bisa
   memperlama).
5. **`delivering` wajib punya resi.** Sekarang `TransactionUpdateRequest`
   (`tracking_number` nullable, :18) dan `TransactionDetail.vue` (:84-112)
   membolehkan `delivering` tanpa resi, jadi penjual bisa lolos dari tenggat
   tanpa mengirim. Ditutup di server (Perubahan 6).

## Perubahan

### 1. Migrasi `add_ship_deadline_to_transactions_table`

```php
$table->timestamp('paid_at')->nullable();
$table->timestamp('ship_deadline_at')->nullable();
$table->index(['delivery_status', 'ship_deadline_at']);
```

Hanya kolom, **tanpa backfill**. Pesanan lama yang dibayar tapi belum dikirim
diberi tenggat oleh command (catch-up, Perubahan 4), bukan oleh migrasi:

- `docker-entrypoint.sh` menjalankan `migrate` (:54) sebelum `config:clear` /
  `config:cache` (:57-60), jadi migrasi bisa membaca config cache lama tanpa
  `ship_deadline_days`.
- Selama deploy, kode lama masih bisa menerima webhook dan memanggil `markPaid()`
  versi lama (tanpa tenggat) setelah migrasi jalan. Backfill sekali di migrasi
  melewatkan pesanan itu; catch-up di setiap run tidak.
- `paid_at` pesanan lama tetap `null` (waktu bayar sebenarnya tidak diketahui).
  Mereka dapat tenggat penuh dihitung dari run pertama, jadi tidak ada
  pembatalan massal saat rilis.
- `Transaction::$casts`: `paid_at`, `ship_deadline_at` => `datetime`.
  Tidak masuk `$fillable` (hanya diisi server).

### 2. `config/marketplace.php`

```php
'ship_deadline_days' => max(1, (int) env('SHIP_DEADLINE_DAYS', 3)),
```

Dijepit: env kosong/0/negatif/bukan angka tidak boleh membuat semua pesanan
langsung lewat tenggat.

### 3. `TransactionRepository::markPaid()`

```php
public function markPaid(Transaction $transaction): void
{
    $transaction->payment_status = 'paid';
    $transaction->paid_at = now();
    $transaction->ship_deadline_at = now()->addDays(config('marketplace.ship_deadline_days'));
    $transaction->save();

    $this->escrowRepository->credit($transaction);
}
```

Tiga pemanggil, semuanya sudah menolak transaksi yang sudah `paid` sebelum
memanggil: webhook `MidtransController` (:243-250), cek status manual
`TransactionController` (:586-589), `payWithBalance()` di checkout. Panggilan
kedua memang tidak didukung (`escrowRepository->credit()` gagal di `unique_ref`),
jadi tidak perlu `??=`; idempotensi dites lewat webhook/cek status ganda.

### 4. Command `transaction:cancel-unshipped`

`app/Console/Commands/CancelUnshippedOrders.php`, pola `EscalateComplaints`:

```php
class CancelUnshippedOrders extends Command
{
    protected $signature = 'transaction:cancel-unshipped';

    protected $description = 'Cancel and refund paid orders the seller did not ship before ship_deadline_at.';

    public const REASON = 'Penjual tidak mengirim pesanan sebelum batas waktu';

    public function handle(TransactionRepositoryInterface $transactionRepository): void
    {
        // Catch-up: orders paid by a path that set no deadline (old code still
        // serving during a deploy, orders paid before this feature) get a full one now.
        self::unshipped()->whereNull('ship_deadline_at')
            ->update(['ship_deadline_at' => now()->addDays(config('marketplace.ship_deadline_days'))]);

        // Keyset by id over the whole due set: an order that keeps failing is
        // tried once per run and cannot block the ones after it.
        $due = self::unshipped()->where('ship_deadline_at', '<=', now())->select('id');

        foreach ($due->lazyById(200) as $row) {
            try {
                // Re-checked under the lock: a seller who shipped meanwhile wins.
                $transaction = $transactionRepository->cancelPaidOrder($row->id, self::REASON);
            } catch (\Throwable $e) {
                // Only a state change (seller shipped or cancelled first) is a
                // skip. A 422 from EscrowRepository::refund() (missing wallet or
                // ledger) on a still-eligible order is a real failure.
                $stillDue = self::unshipped()->whereKey($row->id)->exists();
                Log::log($stillDue ? 'error' : 'info', 'transaction:cancel-unshipped '.($stillDue ? 'failed' : 'skipped'),
                    ['transaction' => $row->id, 'error' => $e->getMessage()]);

                continue;
            }

            // Committed: a broadcast/push failure must not look like a skip, and
            // must not retry the refund.
            try {
                event(new TransactionStatusUpdated($transaction));
            } catch (\Throwable $e) {
                Log::error('transaction:cancel-unshipped notify failed', ['transaction' => $transaction->code, 'error' => $e->getMessage()]);
            }
            $this->info("Cancelled unshipped order {$transaction->code}");
        }
    }

    private static function unshipped(): Builder
    {
        return Transaction::where('payment_status', 'paid')->whereIn('delivery_status', ['pending', 'processing']);
    }
}
```

- Import: `Illuminate\Database\Eloquent\Builder`, `App\Events\TransactionStatusUpdated`,
  `App\Models\Transaction`, `Log`.
- Tidak perlu cek ulang tenggat di dalam kunci: `ship_deadline_at` tidak pernah
  berubah setelah diisi, dan state (`paid` + `pending|processing`) sudah dicek
  ulang di `refundPaidOrder()` di bawah kunci.
- `event(TransactionStatusUpdated)` sama dengan yang dilakukan
  `TransactionController::cancel()`: broadcast Reverb + push/email ke pembeli
  lewat `SendPushOnTransactionStatusUpdated` / `RefundMessage`.
- Komplain tidak relevan: komplain hanya bisa dibuat saat `delivering`.

`routes/console.php`:

```php
// Seller missed the ship deadline on a paid order: cancel and refund the buyer.
Schedule::command('transaction:cancel-unshipped')->everyFifteenMinutes()->withoutOverlapping();
```

### 5. `OpsCheck`: pesanan lewat tenggat tidak terbatalkan

Tiru `complaintsPastDeadline()`: pesanan `paid` + `pending|processing` dengan
`ship_deadline_at < now()->subHour()` → problem `orders-past-ship-deadline`:
"N pesanan lewat batas kirim lebih dari 1 jam tapi belum dibatalkan: ...
Scheduler transaction:cancel-unshipped tidak berjalan, atau pembatalannya gagal
(cari 'transaction:cancel-unshipped failed' di log)." Refund yang macet di
`processing` sudah ditangkap `stuckRefunds()`.

### 6. `delivering` wajib resi

`TransactionRepository::updateStatus()`, di dalam kunci, setelah validasi state
dan **sebelum** `delivery_proof` disimpan ke disk (rollback hanya membatalkan SQL,
file yang sudah ditulis jadi yatim):

```php
$entering = ($data['delivery_status'] ?? null) === 'delivering' && $transaction->delivery_status !== 'delivering';
if ($entering && blank($data['tracking_number'] ?? $transaction->tracking_number)) {
    throw new Exception('Nomor resi wajib diisi', 422);
}
```

Hanya saat **masuk** `delivering`. Pesanan lama yang sudah `delivering` tanpa
resi tetap boleh edit bukti kirim seperti sekarang (tidak dipaksa remediasi).
Resi efektif = yang baru dikirim atau yang sudah tersimpan. `TransactionDetail.vue`
menonaktifkan tombol kirim selama resi kosong (`IncomingOrders.vue` :73 sudah).

### 7. API + FE

- `TransactionResource`: tambah `paid_at`, `ship_deadline_at`.
- Seller (`fe-blue` `views/admin/order/IncomingOrders.vue`, `TransactionDetail.vue`):
  untuk `paid` + `pending|processing`, tampilkan "Kirim sebelum d LLL yyyy, HH:mm"
  + sisa jam (pola `ComplaintPanel.vue` :43-55), merah bila < 24 jam.
- Buyer (detail transaksi): "Penjual wajib mengirim sebelum ... Jika tidak,
  pesanan dibatalkan dan dana dikembalikan otomatis."
- Seller yang membuka pesanan yang sudah dibatalkan otomatis lalu mencoba update
  status sekarang dapat "Pesanan belum dibayar" (karena `payment_status` jadi
  `failed`). Perbaiki pesan di `updateStatus()`: kalau `delivery_status ===
  'cancelled'`, lempar "Pesanan sudah dibatalkan" (422).

## Tes

`tests/Feature/CancelUnshippedOrdersTest.php` (setup seperti
`SellerCancelRefundTest`, `Queue::fake()`, `travelTo`):

1. Bayar lewat webhook settlement, lewat cek status manual, dan lewat Saldo
   penuh → `paid_at` terisi, `ship_deadline_at = paid_at + 3 hari`. Webhook /
   cek status ganda tidak menggeser keduanya dan escrow tetap satu kredit.
2. Pesanan `pending` dan `processing` lewat tenggat → `cancelled`,
   `payment_status = failed`, `refund_status = processing`,
   `refund_reason = REASON`, stok kembali, escrow ditarik,
   `RefundCancelledTransactionJob` di-dispatch, event terpancar.
3. Dibayar penuh pakai Saldo → `refund_status = refunded`, `refund_method = balance`,
   saldo pembeli kembali, tidak ada job.
4. Bayar campuran Saldo + Midtrans → `balance_used` kembali ke Saldo,
   `refund_amount` hanya bagian Midtrans. Satu pembayaran untuk beberapa toko
   (`payment_code` sama): hanya pesanan toko yang telat yang batal.
5. Tepat di tenggat (`ship_deadline_at == now()`) batal; satu detik sebelumnya tidak.
   Tidak disentuh: `delivering`, `completed`, belum dibayar, sudah `cancelled`.
6. Catch-up: pesanan `paid` + `processing` dengan `ship_deadline_at = null` dapat
   tenggat `now + 3 hari` dan tidak batal di run itu; `paid_at` tetap null.
7. Command dua kali, lalu webhook settlement telat setelah batal otomatis →
   refund/saldo/stok/escrow tidak dobel.
8. Pesanan tertua gagal terus (ledger escrow dihapus) → dicatat `failed`,
   pesanan sesudahnya tetap dibatalkan di run yang sama.
9. Masuk `delivering` tanpa resi (dengan foto bukti) → 422 dan tidak ada file
   tersimpan di disk; edit bukti pada `delivering` yang punya resi → lolos;
   edit bukti pada `delivering` lama tanpa resi → tetap lolos.
11. Event `TransactionStatusUpdated` melempar setelah batal → log
    `notify failed` (error), pesanan tetap batal, refund tidak diulang.
10. Race di PostgreSQL (harness `P0EscrowConcurrencyTest`): penjual input resi vs
    command, dua urutan kunci → tepat satu hasil, tanpa refund untuk pesanan
    yang terkirim.

## Di luar cakupan v1

- Celah lama, fix terpisah: `RefundCancelledTransactionJob::leaveForAdmin()`
  (:109-115) menaruh refund ambigu di `manual_required` tanpa rekening, dan
  `OpsCheck::manualRefunds()` (:363) hanya memantau yang sudah punya rekening.
  Fitur ini menambah volume refund, jadi layak dikerjakan berdekatan: alert
  `manual_required` yang belum selesai > N jam, apa pun isi rekeningnya.
- Pengingat email/push ke penjual sebelum tenggat (mis. H-1). Tambah bila
  angka batal otomatis tinggi; UI countdown dulu.
- Notifikasi ke penjual saat pesanannya dibatalkan otomatis (penjual lihat di
  dashboard). Tambah bersama pengingat.
- Perpanjangan tenggat oleh penjual/admin, hari kerja/libur nasional,
  tenggat per toko.
- Penalti/skor toko untuk batal otomatis.
- Resi palsu/tidak valid (cek ke API kurir).
- App mobile Flutter (repo terpisah): `seller_order_detail_screen.dart` masih
  mengirim `delivering` dengan resi kosong (resi opsional di sana), sekarang
  dijawab 422 "Nomor resi wajib diisi". Perlu: nonaktifkan tombol kirim saat
  resi kosong, dan tampilkan `ship_deadline_at`.
- Catatan terlihat: `transaction:auto-complete` memakai `updated_at` sebagai
  waktu mulai `delivering`; tidak diubah di sini.
