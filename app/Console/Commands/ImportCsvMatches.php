<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\Fixture;
use App\Models\League;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ImportCsvMatches extends Command
{
    protected $signature = 'import:csv-matches {file}';
    protected $description = 'Импорт матчей сборных из CSV-файла (без external_id)';

    public function handle()
    {
        $file = $this->argument('file');
        if (!file_exists($file)) {
            $this->error("Файл не найден: $file");
            return 1;
        }

        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);
        if (!$header) {
            $this->error('Пустой файл');
            return 1;
        }

        $this->info('Импорт...');
        $count = 0;
        DB::beginTransaction();
        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);
            $date = Carbon::parse($data['date']);
            $homeTeamName = trim($data['home_team']);
            $awayTeamName = trim($data['away_team']);
            $homeScore = (int) $data['home_score'];
            $awayScore = (int) $data['away_score'];
            $tournament = $data['tournament'];

            // Лига – не задаём external_id
            $league = League::firstOrCreate(
                ['name' => $tournament],
                [
                    'external_id' => null,
                    'country' => 'International',
                    'type' => 'tournament'
                ]
            );

            // Команды – не задаём external_id
            $homeTeam = Team::firstOrCreate(['name' => $homeTeamName], ['external_id' => null]);
            $awayTeam = Team::firstOrCreate(['name' => $awayTeamName], ['external_id' => null]);

            // Матч – не задаём external_id
            Fixture::updateOrCreate(
                [
                    'starting_at' => $date,
                    'home_team_id' => $homeTeam->id,
                    'away_team_id' => $awayTeam->id,
                ],
                [
                    'league_id'    => $league->id,
                    'home_score'   => $homeScore,
                    'away_score'   => $awayScore,
                    'status'       => 'FT',
                    'external_id'  => null,
                ]
            );
            $count++;
            if ($count % 1000 == 0) $this->line("Импортировано $count матчей");
        }
        DB::commit();
        $this->info("Готово! Импортировано $count матчей.");
        return 0;
    }
}