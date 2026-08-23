<?php

namespace App\Models;

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
        // MATCH...AGAINST is MySQL-only syntax — sqlite (used by the test
        // suite) doesn't understand it at all and throws a raw SQL syntax
        // error, which meant this scope was never actually exercised by any
        // test with a 3+ char term. LIKE-only here isn't a downgrade for
        // testing purposes (still validates the matching behavior itself,
        // just without MySQL's relevance ranking), and production keeps its
        // real FULLTEXT index.
        if (mb_strlen($search) >= 3 && $query->getConnection()->getDriverName() === 'mysql') {
            $safeTerm = preg_replace('/[+\-*"<>()~@]+/', '', $search);
            if (mb_strlen(trim($safeTerm)) >= 3) {
                return $query->whereRaw('MATCH(name, description) AGAINST(? IN BOOLEAN MODE)', ['+'.$safeTerm.'*']);
            }
        }

        return $query->where('name', 'like', '%'.$search.'%');
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
