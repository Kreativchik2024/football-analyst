<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchStatistic extends Model
{
    protected $table = 'match_statistics'; // имя таблицы в БД

    protected $fillable = [
        'fixture_id',
        'stat_type',
        'home_value',
        'away_value',
    ];

    public function fixture()
    {
        return $this->belongsTo(Fixture::class);
    }
}