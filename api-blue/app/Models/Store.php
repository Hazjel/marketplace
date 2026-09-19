<?php

namespace App\Models;

use App\Support\PostgresSearch;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory, UUID;

    protected $fillable = [
        'user_id',
        'name',
        'username',
        'logo',
        'about',
        'phone',
        'address_id',
        'city',
        'address',
        'postal_code',
        'latitude',
        'longitude',
        'is_verified',
        'is_active',
        'ai_assistant_enabled',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'ai_assistant_enabled' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function scopeSearch($query, $search)
    {
        // Guard driver-nya wajib: sebelum ini scope memakai MATCH...AGAINST
        // tanpa cek apa pun, jadi sqlite di test suite pun ikut kena sintaks
        // yang tidak dipahaminya. Sekarang hanya Postgres yang memakai FTS,
        // sisanya jatuh ke LIKE di bawah.
        if (mb_strlen($search) >= 3 && $query->getConnection()->getDriverName() === 'pgsql') {
            $tsQuery = PostgresSearch::tsQuery($search);
            if ($tsQuery !== null) {
                return $query->where(function ($q) use ($tsQuery, $search) {
                    $q->whereRaw(
                        PostgresSearch::tsVector(['name'])." @@ to_tsquery('".PostgresSearch::CONFIG."', ?)",
                        [$tsQuery]
                    )->orWhere('phone', PostgresSearch::likeOperator(), '%'.$search.'%');
                });
            }
        }

        return $query->where('name', PostgresSearch::likeOperator(), '%'.$search.'%')
            ->orWhere('phone', PostgresSearch::likeOperator(), '%'.$search.'%');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function storeBalance()
    {
        return $this->hasOne(StoreBalance::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function transaction()
    {
        return $this->hasMany(Transaction::class);
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'store_followers', 'store_id', 'user_id')->withTimestamps();
    }
}
