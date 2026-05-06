<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bookmaker extends Model
{
    protected $fillable = [
        'external_id',
        'name',
        'country',      // ← добавляем
        'logo_url',
        'is_active',
    ];

    public function odds(): HasMany
    {
        return $this->hasMany(Odd::class);
    }
}