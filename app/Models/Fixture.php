<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}