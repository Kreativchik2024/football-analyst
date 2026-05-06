<?php

namespace App\Console\Commands;

use App\Models\Fixture;
use App\Services\SettlementService;
use Illuminate\Console\Command;

class SettleBets extends Command
{
    protected $signature = 'bets:settle';
    protected $description = 'Расчёт всех открытых ставок пользователей';

    public function handle(SettlementService $settlement)
    {
        $fixtures = Fixture::whereIn('status', ['FT', 'AET', 'PEN'])
            ->whereHas('userPredictions', function ($q) {
                $q->where('status', 'pending');
            })->get();

        foreach ($fixtures as $fixture) {
            $settlement->settle($fixture);
        }

        $this->info('Ставки рассчитаны.');
    }
}