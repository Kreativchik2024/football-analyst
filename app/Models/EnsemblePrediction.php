<?php
// app/Models/EnsemblePrediction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnsemblePrediction extends Model
{
    protected $table = 'ensemble_predictions';
    
    protected $fillable = [
        'fixture_id',
        'home_probability',
        'draw_probability',
        'away_probability',
        'confidence',
        'models_used',
        'model_version',
    ];
    
    protected $casts = [
        'models_used' => 'array',
    ];
    
    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }
}