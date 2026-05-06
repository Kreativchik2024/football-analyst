<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $fillable = [
        'external_id',
        'name',
        'short_code',
        'country',
        'logo_url',
    ];

    public function homeMatches(): HasMany
    {
        return $this->hasMany(Fixture::class, 'home_team_id');
    }

    public function awayMatches(): HasMany
    {
        return $this->hasMany(Fixture::class, 'away_team_id');
    }
    public function injuries(): HasMany
{
    return $this->hasMany(Injury::class);
}
}