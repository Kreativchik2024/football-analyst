<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

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

    protected $casts = [
        'settled_at' => 'datetime',
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

    // ========== Scopes ==========

    /**
     * Фильтр по ставкам для прематча
     */
    public function scopePrematch(Builder $query): Builder
    {
        return $query->where('type', 'prematch');
    }

    /**
     * Фильтр по лайв-ставкам
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('type', 'live');
    }

    /**
     * Фильтр по ожидающим ставкам
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Сортировка по ожидаемой ценности
     */
    public function scopeByExpectedValue(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('expected_value', $direction);
    }
}