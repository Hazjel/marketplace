<?php

namespace App\Models;

use App\Support\PostgresSearch;
use App\Traits\UUID;
use Brick\Math\BigInteger;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Eloquent\HybridRelations;

class Product extends Model
{
    use HasFactory, HybridRelations, UUID;

    /** "Terlaris": at least this many units sold in paid orders ... */
    public const BEST_SELLER_MIN_QTY = 10;

    /** ... within this many days, by payment time (order time before paid_at existed). */
    public const BEST_SELLER_DAYS = 30;

    /** Upper bound for price and compare_at_price (Rp1 triliun): keeps the discount math in int range. */
    public const MAX_PRICE = 1_000_000_000_000;

    protected $fillable = [
        'store_id',
        'product_category_id',
        'name',
        'slug',
        'description',
        'condition',
        'price',
        'compare_at_price',
        'weight',
        'stock',
        'has_variants',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'weight' => 'decimal:2',
        'stock' => 'integer',
        'has_variants' => 'boolean',
    ];

    public function scopeSearch($query, $search)
    {
        // Full-text search hanya ada di Postgres — sqlite (dipakai test suite)
        // tidak paham to_tsvector/to_tsquery dan langsung melempar syntax
        // error, jadi di sana scope ini jatuh ke LIKE. Itu bukan penurunan
        // untuk keperluan test (perilaku pencocokannya tetap teruji, hanya
        // tanpa peringkat relevansi), dan produksi tetap memakai index GIN
        // dari migrasi add_fulltext_indexes_to_products_and_stores.
        if (mb_strlen($search) >= 3 && $query->getConnection()->getDriverName() === 'pgsql') {
            $tsQuery = PostgresSearch::tsQuery($search);
            if ($tsQuery !== null) {
                return $query->whereRaw(
                    PostgresSearch::tsVector(['name', 'description'])." @@ to_tsquery('".PostgresSearch::CONFIG."', ?)",
                    [$tsQuery]
                );
            }
        }

        return $query->where('name', PostgresSearch::likeOperator(), '%'.$search.'%');
    }

    /**
     * Adds recent_sold_qty (paid units in the best-seller window) from one
     * grouped aggregate over the window's orders, joined once. A correlated
     * SUM per product would rescan transaction_details for every row.
     */
    public function scopeWithRecentSold($query)
    {
        $recentSales = DB::table('transaction_details')
            ->join('transactions', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->where('transactions.payment_status', 'paid')
            // paid_at is null on orders paid before the column existed.
            ->whereRaw('COALESCE(transactions.paid_at, transactions.created_at) >= ?', [now()->subDays(self::BEST_SELLER_DAYS)])
            ->groupBy('transaction_details.product_id')
            ->select('transaction_details.product_id', DB::raw('SUM(transaction_details.qty) as qty'));

        if ($query->getQuery()->columns === null) {
            $query->select('products.*');
        }

        return $query
            ->leftJoinSub($recentSales, 'recent_sales', 'recent_sales.product_id', '=', 'products.id')
            ->addSelect(DB::raw('COALESCE(recent_sales.qty, 0) as recent_sold_qty'));
    }

    /**
     * Whole-percent discount (floored) shown next to a struck-through price,
     * or null when there is no valid "harga sebelum diskon".
     */
    public static function discountPercent(int $price, ?int $compareAtPrice): ?int
    {
        if ($compareAtPrice === null || $compareAtPrice <= $price) {
            return null;
        }

        // BigInteger: (difference x 100) can pass PHP_INT_MAX on rows that
        // predate the MAX_PRICE cap. A sliver above price floors to 0: no "-0%".
        return BigInteger::of($compareAtPrice - $price)->multipliedBy(100)->quotient($compareAtPrice)->toInt() ?: null;
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function productCategory()
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function productImages()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function transactionDetails()
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function productReviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariantMongo::class, 'product_id', 'id');
    }

    public function getTotalSoldAttribute(): int
    {
        // Kalau query pemanggil sudah menyertakan withSum(), pakai hasilnya.
        // Tanpa ini setiap produk yang diserialisasi memicu satu SUM sendiri —
        // N+1 yang tidak kelihatan karena tersembunyi di balik accessor.
        if (array_key_exists('transaction_details_sum_qty', $this->attributes)) {
            return (int) $this->attributes['transaction_details_sum_qty'];
        }

        // SUM() lewat PDO balik sebagai string, jadi endpoint yang lewat sini
        // dulu mengirim "1" sementara listing mengirim 1. Klien yang mengurai
        // angka ini secara ketat pecah di salah satu jalur; samakan ke int.
        return (int) $this->transactionDetails()
            ->whereHas('transaction', function ($q) {
                $q->where('payment_status', 'paid');
            })
            ->sum('qty');
    }
}
