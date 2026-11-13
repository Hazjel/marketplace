<?php

namespace App\Models;

use App\Support\PostgresSearch;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\HybridRelations;

class Product extends Model
{
    use HasFactory, HybridRelations, UUID;

    /** "Terlaris": at least this many units sold in paid orders ... */
    public const BEST_SELLER_MIN_QTY = 10;

    /** ... within this many days (by order date). */
    public const BEST_SELLER_DAYS = 30;

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
     * Adds recent_sold_qty (paid units in the best-seller window) as one
     * correlated SUM, so the resource can emit is_best_seller without N+1.
     */
    public function scopeWithRecentSold($query)
    {
        return $query->withSum(['transactionDetails as recent_sold_qty' => function ($q) {
            $q->whereHas('transaction', function ($t) {
                $t->where('payment_status', 'paid')
                    ->where('created_at', '>=', now()->subDays(self::BEST_SELLER_DAYS));
            });
        }], 'qty');
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

        // A sliver above price floors to 0: no "-0%" label.
        return intdiv(($compareAtPrice - $price) * 100, $compareAtPrice) ?: null;
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
