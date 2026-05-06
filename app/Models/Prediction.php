<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prediction extends Model
{
    protected $fillable = [
        'fixture_id',
        'home_probability',
        'draw_probability',
        'away_probability',
        'model_version',
        'features_used',
    ];

    protected $casts = [
        'features_used' => 'array',
    ];

    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }

    public function valueBets(): HasMany
    {
        return $this->hasMany(ValueBet::class);
    }
}