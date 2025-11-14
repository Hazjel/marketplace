<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Testing\Fluent\Concerns\Has;

class Buyer extends Model
{
    use UUID, HasFactory;

    protected $fillable = [
        'user_id',
        'profile_picture',
        'phone_number'
    ];

    public function scopeSearch($query, $search)
    {
        return $query->whereHas('user', function($q) use ($search){
            $q->where('name', 'LIKE', "%".$search."%");
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transaction()
    {
        return $this->hasMany(Transaction::class);
    }
}
