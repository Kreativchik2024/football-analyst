<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyBriefing extends Model
{
    protected $fillable = [
        'briefing_date',
        'content',
    ];

    protected $casts = [
        'briefing_date' => 'date',
    ];
}