<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPrediction extends Model
{
    protected $fillable = [
        'user_id', 'fixture_id', 'bet_type', 'stake', 'odds', 'status', 'profit', 'settled_at'
    ];

    protected $casts = [
        'settled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fixture()
    {
        return $this->belongsTo(Fixture::class);
    }
}