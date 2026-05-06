<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchEvent extends Model
{
    protected $fillable = [
        'fixture_id',
        'event_type',
        'detail',
        'player_name',
        'assist_name',
        'elapsed',
        'team_type',
    ];

    public function fixture()
    {
        return $this->belongsTo(Fixture::class);
    }
}