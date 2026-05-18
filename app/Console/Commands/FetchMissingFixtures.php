<?php
// app/Console/Commands/FetchMissingFixtures.php

namespace App\Console\Commands;

use App\Models\Fixture;
use App\Models\League;
use App\Models\Team;
use App\Services\ApiFootballService;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class FetchMissingFixtures extends Command
{
    protected $signature = 'fetch:missing 
                            {--days=30 : За сколько дней проверить}
                            {--league= : ID лиги (опционально)}
                            {--execute : Реально загрузить матчи (без этого только показать)}';

    protected $description = 'Найти и загрузить пропущенные матчи';

    protected $apiKey;
    protected $baseUrl = 'https://v3.football.api-sports.io';

    public function __construct()
    {
        parent::__construct();
        $this->apiKey = env('API_FOOTBALL_KEY');
    }

    public function handle()
    {
        $days = (int) $this->option('days');
        $execute = $this->option('execute');
        
        $this->info("🔍 ПОИСК ПРОПУЩЕННЫХ МАТЧЕЙ");
        $this->line("Период: последние {$days} дней");
        
        $from = Carbon::now()->subDays($days)->toDateString();
        $to = Carbon::now()->toDateString();
        
        // Получаем сезон
        $year = Carbon::now()->year;
        $season = Carbon::now()->month >= 8 ? $year : $year - 1;
        
        // Лиги для проверки
        if ($this->option('league')) {
            $leagueIds = [(int) $this->option('league')];
        } else {
            // Основные лиги
            $leagueIds = [39, 40, 140, 135, 78, 61, 94, 71, 235, 1, 2, 3, 13];
        }
        
        $totalMissing = 0;
        $totalLoaded = 0;
        
        foreach ($leagueIds as $leagueId) {
            $this->info("\nЛига ID: {$leagueId}");
            
            try {
                // Получаем матчи из API
                $response = Http::withHeaders([
                    'x-apisports-key' => $this->apiKey,
                ])
                    ->timeout(30)
                    ->get("{$this->baseUrl}/fixtures", [
                        'league' => $leagueId,
                        'season' => $season,
                        'from'   => $from,
                        'to'     => $to,
                    ]);
                
                if (!$response->successful()) {
                    $this->warn("  Ошибка API: " . $response->status());
                    continue;
                }
                
                $apiFixtures = $response->json()['response'] ?? [];
                $this->line("  В API: " . count($apiFixtures) . " матчей");
                
                // Получаем ID матчей из БД
                $leagueModel = League::where('external_id', $leagueId)->first();
                if (!$leagueModel) {
                    $this->warn("  Лига не найдена в БД, пропускаем");
                    continue;
                }
                
                $existingIds = Fixture::where('league_id', $leagueModel->id)
                    ->pluck('external_id')
                    ->toArray();
                
                // Находим пропущенные
                $missing = [];
                foreach ($apiFixtures as $fixture) {
                    $apiId = $fixture['fixture']['id'];
                    if (!in_array($apiId, $existingIds)) {
                        $missing[] = $fixture;
                    }
                }
                
                if (empty($missing)) {
                    $this->line("  ✅ Пропущенных нет");
                    continue;
                }
                
                $this->warn("  Найдено пропущенных: " . count($missing));
                
                // Загружаем пропущенные
                foreach ($missing as $fixture) {
                    $this->line("    Загрузка матча ID: {$fixture['fixture']['id']}");
                    
                    if ($execute) {
                        $this->saveFixture($fixture, $leagueModel->id);
                        $totalLoaded++;
                    }
                    
                    usleep(100000); // пауза
                }
                
                $totalMissing += count($missing);
                
            } catch (\Exception $e) {
                $this->error("  Ошибка: " . $e->getMessage());
            }
            
            sleep(1); // пауза между лигами
        }
        
        $this->newLine();
        $this->info("📊 ИТОГО:");
        $this->line("  Найдено пропущенных: {$totalMissing}");
        if ($execute) {
            $this->info("  ✅ Загружено: {$totalLoaded}");
        } else {
            $this->line("\n💡 Чтобы реально загрузить матчи, запустите с флагом --execute");
            $this->line("   php artisan fetch:missing --days={$days} --execute");
        }
    }
    
    private function saveFixture($data, $leagueId)
    {
        $fixtureInfo = $data['fixture'];
        $homeTeamData = $data['teams']['home'];
        $awayTeamData = $data['teams']['away'];
        
        // Сохраняем команды
        $homeTeam = Team::updateOrCreate(
            ['external_id' => $homeTeamData['id']],
            [
                'name'       => $homeTeamData['name'],
                'short_code' => $homeTeamData['code'] ?? null,
                'logo_url'   => $homeTeamData['logo'] ?? null,
            ]
        );
        
        $awayTeam = Team::updateOrCreate(
            ['external_id' => $awayTeamData['id']],
            [
                'name'       => $awayTeamData['name'],
                'short_code' => $awayTeamData['code'] ?? null,
                'logo_url'   => $awayTeamData['logo'] ?? null,
            ]
        );
        
        // Сохраняем матч
        Fixture::updateOrCreate(
            ['external_id' => $fixtureInfo['id']],
            [
                'league_id'    => $leagueId,
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $awayTeam->id,
                'starting_at'  => Carbon::parse($fixtureInfo['date']),
                'status'       => $fixtureInfo['status']['short'] ?? 'NS',
                'home_score'   => $data['goals']['home'] ?? null,
                'away_score'   => $data['goals']['away'] ?? null,
            ]
        );
    }
}