<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\Fixture;
use Illuminate\Console\Command;

class CalculateElo extends Command
{
    protected $signature = 'calculate:elo {--recalc : Сбросить ELO до 1500 перед расчётом}';
    protected $description = 'Рассчитывает ELO рейтинг для всех команд на основе истории матчей';

    public function handle()
    {
        if ($this->option('recalc')) {
            Team::query()->update(['elo_rating' => 1500]);
            $this->info('ELO сброшено до 1500.');
        }

        $fixtures = Fixture::with(['homeTeam', 'awayTeam'])
            ->orderBy('starting_at')
            ->get();

        $this->info("Обрабатывается {$fixtures->count()} матчей...");
        $bar = $this->output->createProgressBar($fixtures->count());
        $bar->start();

        foreach ($fixtures as $fixture) {
            $home = $fixture->homeTeam;
            $away = $fixture->awayTeam;
            if (!$home || !$away) continue;

            $eloHome = $home->elo_rating ?? 1500;
            $eloAway = $away->elo_rating ?? 1500;

            $expectedHome = 1 / (1 + pow(10, ($eloAway - $eloHome) / 400));
            $expectedAway = 1 - $expectedHome;

            $homeScore = $fixture->home_score;
            $awayScore = $fixture->away_score;
            $resultHome = $homeScore > $awayScore ? 1 : ($homeScore == $awayScore ? 0.5 : 0);
            $resultAway = 1 - $resultHome;

            $k = 32; // для сборных можно использовать 32 или 20
            $newEloHome = $eloHome + $k * ($resultHome - $expectedHome);
            $newEloAway = $eloAway + $k * ($resultAway - $expectedAway);

            $home->elo_rating = round($newEloHome);
            $away->elo_rating = round($newEloAway);
            $home->save();
            $away->save();

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Расчёт ELO завершён.');
    }
}