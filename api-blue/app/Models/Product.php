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

    protected $fillable = [
        'store_id',
        'product_category_id',
        'name',
        'slug',
        'description',
        'condition',
        'price',
        'weight',
        'stock',
        'has_variants',
    ];

    protected $casts = [
        'price' => 'decimal:2',
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
