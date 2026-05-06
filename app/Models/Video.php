<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Video extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'embed_code', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}