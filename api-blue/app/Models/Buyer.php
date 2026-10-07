<?php

namespace App\Models;

use App\Support\PostgresSearch;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Buyer extends Model
{
    use HasFactory, UUID;

    protected $fillable = [
        'user_id',
        'phone_number',
    ];

    // Saldo Blukios is shown only to its owner (GET /api/balance); the raw
    // buyer row is embedded in /me and other resources.
    protected $hidden = [
        'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->whereHas('user', function ($q) use ($search) {
            $q->where('name', PostgresSearch::likeOperator(), '%'.$search.'%');
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function balanceHistories()
    {
        return $this->hasMany(BuyerBalanceHistory::class);
    }

    public function transaction()
    {
        return $this->hasMany(Transaction::class);
    }
}
