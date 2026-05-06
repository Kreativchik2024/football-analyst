<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValueBet extends Model
{
    protected $fillable = [
    'fixture_id',
    'prediction_id',
    'odd_id',
    'bet_type',
    'expected_value',
    'edge_percent',
    'explanation',   // ← новое поле
    'status',
    'profit',
    'settled_at',
];

    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class);
    }

    public function odd(): BelongsTo
    {
        return $this->belongsTo(Odd::class);
    }
}