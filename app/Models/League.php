<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class League extends Model
{
    protected $fillable = [
        'external_id',
        'name',
        'country',
        'type',
        'logo_url',
        'is_active',
    ];

    public function matches(): HasMany
    {
        return $this->hasMany(Fixture::class);
    }
}