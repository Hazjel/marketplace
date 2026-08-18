<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    use UUID;

    protected $fillable = [
        'user_id',
        'token',
        'platform',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
