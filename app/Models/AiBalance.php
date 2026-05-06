<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiBalance extends Model
{
    protected $table = 'ai_balance';
    protected $fillable = ['balance'];

    public static function getBalance(): float
    {
        return self::firstOrCreate([], ['balance' => 100000])->balance;
    }

    public static function updateBalance(float $amount): void
    {
        $balance = self::firstOrCreate([], ['balance' => 100000]);
        $balance->balance += $amount;
        $balance->save();
    }
}