<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\Fixture;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateForm extends Command
{
    protected $signature = 'calculate:form 
                            {--matches=5 : Количество последних матчей для расчёта формы}
                            {--recalc : Пересчитать форму для всех команд (сбросить предыдущие значения)}';
    protected $description = 'Рассчитывает форму команд (средние голы за последние N матчей) на основе истории';

    public function handle()
    {
        $limit = (int) $this->option('matches');
        $recalc = $this->option('recalc');

        if ($recalc) {
            Team::query()->update([
                'form_goals_scored_avg' => null,
                'form_goals_conceded_avg' => null,
                'form_points_avg' => null,
                'form_matches_count' => 0,
            ]);
            $this->info('Форма сброшена.');
        }

        $teams = Team::all();
        $this->info("Обработка {$teams->count()} команд...");
        $bar = $this->output->createProgressBar($teams->count());
        $bar->start();

        foreach ($teams as $team) {
            // Получаем последние N матчей команды (дома и в гостях) до сегодняшнего дня
            $fixtures = Fixture::where(function ($q) use ($team) {
                $q->where('home_team_id', $team->id)
                  ->orWhere('away_team_id', $team->id);
            })
            ->where('starting_at', '<', now())
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->orderBy('starting_at', 'desc')
            ->limit($limit)
            ->get();

            $totalGoalsScored = 0;
            $totalGoalsConceded = 0;
            $totalPoints = 0;
            $matchesCount = $fixtures->count();

            foreach ($fixtures as $fixture) {
                $isHome = ($fixture->home_team_id == $team->id);
                $goalsScored = $isHome ? $fixture->home_score : $fixture->away_score;
                $goalsConceded = $isHome ? $fixture->away_score : $fixture->home_score;

                $totalGoalsScored += $goalsScored;
                $totalGoalsConceded += $goalsConceded;

                if ($goalsScored > $goalsConceded) {
                    $totalPoints += 3;
                } elseif ($goalsScored == $goalsConceded) {
                    $totalPoints += 1;
                }
            }

            if ($matchesCount > 0) {
                $team->form_goals_scored_avg = round($totalGoalsScored / $matchesCount, 2);
                $team->form_goals_conceded_avg = round($totalGoalsConceded / $matchesCount, 2);
                $team->form_points_avg = round($totalPoints / $matchesCount, 2);
                $team->form_matches_count = $matchesCount;
            } else {
                $team->form_goals_scored_avg = null;
                $team->form_goals_conceded_avg = null;
                $team->form_points_avg = null;
                $team->form_matches_count = 0;
            }
            $team->save();

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Расчёт формы завершён.');
    }
}