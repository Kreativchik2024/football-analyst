<?php

namespace App\Console\Commands;

use App\Models\Fixture;
use Illuminate\Console\Command;
use League\Csv\Writer; // нужен пакет league/csv

class ExportFixturesForML extends Command
{
    protected $signature = 'export:fixtures-ml {--league= : ID лиги} {--season=2023}';
    protected $description = 'Экспортировать завершённые матчи в CSV для обучения ML';

    public function handle()
    {
        // Установите пакет: composer require league/csv
        $fixtures = Fixture::with(['homeTeam', 'awayTeam', 'matchStatistics'])
            ->where('status', 'FT')
            ->when($this->option('league'), fn($q, $id) => $q->where('league_id', $id))
            ->whereYear('starting_at', '>=', $this->option('season'))
            ->get();

        $csv = Writer::createFromString('');
        $csv->insertOne([
            'home_team', 'away_team',
            'home_xg', 'away_xg',
            'home_possession', 'away_possession',
            'home_shots', 'away_shots',
            'home_shots_on_target', 'away_shots_on_target',
            'home_corners', 'away_corners',
            'home_fouls', 'away_fouls',
            'home_offsides', 'away_offsides',
            'home_passes', 'away_passes',
            'result' // 0 – home win, 1 – draw, 2 – away win
        ]);

        foreach ($fixtures as $f) {
            $stats = $f->matchStatistics->keyBy('stat_type');
            $csv->insertOne([
                $f->homeTeam->name ?? '',
                $f->awayTeam->name ?? '',
                $f->home_xg, $f->away_xg,
                $f->home_possession, $f->away_possession,
                $stats['Total Shots']->home_value ?? '', $stats['Total Shots']->away_value ?? '',
                $stats['Shots on Goal']->home_value ?? '', $stats['Shots on Goal']->away_value ?? '',
                $stats['Corner Kicks']->home_value ?? '', $stats['Corner Kicks']->away_value ?? '',
                $stats['Fouls']->home_value ?? '', $stats['Fouls']->away_value ?? '',
                $stats['Offsides']->home_value ?? '', $stats['Offsides']->away_value ?? '',
                $stats['Total Passes']->home_value ?? '', $stats['Total Passes']->away_value ?? '',
                $f->home_score > $f->away_score ? 0 : ($f->home_score == $f->away_score ? 1 : 2),
            ]);
        }

        file_put_contents(storage_path('app/ml_data.csv'), $csv->toString());
        $this->info('Экспортировано матчей: ' . $fixtures->count());
    }
}