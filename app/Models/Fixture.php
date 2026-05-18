<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Fixture extends Model
{
    protected $table = 'fixtures';

    protected $fillable = [
        'external_id',
        'league_id',
        'home_team_id',
        'away_team_id',
        'starting_at',
        'status',
        'home_score',
        'away_score',
        'statistics',
        'home_xg',
        'away_xg',
        'home_possession',
        'away_possession',
    ];

    protected $casts = [
        'starting_at' => 'datetime',
        'statistics'  => 'array',
    ];

    // ========== Связи ==========

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function odds(): HasMany
    {
        return $this->hasMany(Odd::class);
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }

    // ⭐ ДОБАВЬТЕ ЭТУ СВЯЗЬ - для ансамблевого прогноза (одна запись на матч)
    public function ensemblePrediction(): HasOne
    {
        return $this->hasOne(Prediction::class)->where('agent_type', 'ml_ensemble');
    }

    // Дополнительные удобные связи для разных типов прогнозов
    public function apiPrediction(): HasOne
    {
        return $this->hasOne(Prediction::class)->where('agent_type', 'api_football');
    }

    public function xgboostPrediction(): HasOne
    {
        return $this->hasOne(Prediction::class)->where('agent_type', 'xgboost');
    }

    public function orchestratorPrediction(): HasOne
    {
        return $this->hasOne(Prediction::class)->where('agent_type', 'orchestrator');
    }

    public function valueBets(): HasMany
    {
        return $this->hasMany(ValueBet::class);
    }

    public function matchEvents(): HasMany
    {
        return $this->hasMany(MatchEvent::class);
    }

    public function matchStatistics(): HasMany
    {
        return $this->hasMany(MatchStatistic::class);
    }

    // ========== Scopes ==========

    public function scopeUpcoming($query)
    {
        return $query->where('starting_at', '>=', now())
            ->whereIn('status', ['NS', 'TBD', 'PST'])
            ->orderBy('starting_at');
    }

    public function scopeLive($query)
    {
        return $query->whereIn('status', ['LIVE', '1H', 'HT', '2H', 'ET'])
            ->orderBy('starting_at');
    }

    public function scopeFinished($query)
    {
        return $query->whereIn('status', ['FT', 'AET', 'PEN'])
            ->orderBy('starting_at', 'desc');
    }

    public function scopeNextDays($query, int $days = 7)
    {
        return $query->where('starting_at', '>=', now())
            ->where('starting_at', '<=', now()->addDays($days));
    }
    // В модели Fixture добавьте:

}