<?php
// app/Console/Commands/FetchUpcomingFixtures.php

namespace App\Console\Commands;

use App\Models\League;
use App\Models\Team;
use App\Models\Fixture;
use App\Models\Odd;
use App\Services\ApiFootballService;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FetchUpcomingFixtures extends Command
{
    protected $signature = 'fetch:upcoming 
                            {--days=7 : На сколько дней вперед загружать}
                            {--leagues= : Список ID лиг через запятую}
                            {--skip-odds : Пропустить загрузку коэффициентов}
                            {--timeout=10 : Таймаут для API запросов}';

    protected $description = 'Загрузить предстоящие матчи';

    protected array $allLeagueIds = [
        39, 40, 140, 141, 135, 136, 78, 79, 61, 62,
        94, 95, 71, 72, 235, 236, 19, 20, 294, 295, 253, 254,
        1, 2, 3, 4, 13
    ];

    protected ApiFootballService $api;

    public function __construct(ApiFootballService $api)
    {
        parent::__construct();
        $this->api = $api;
    }

    public function handle()
    {
        $days = (int) $this->option('days');
        $skipOdds = $this->option('skip-odds');
        $timeout = (int) $this->option('timeout');
        
        $from = Carbon::now()->toDateString();
        $to = Carbon::now()->addDays($days)->toDateString();

        $currentYear = Carbon::now()->year;
        $season = Carbon::now()->month >= 8 ? $currentYear : $currentYear - 1;

        $leaguesOption = $this->option('leagues');
        if ($leaguesOption) {
            $selectedIds = array_map('intval', explode(',', $leaguesOption));
            $leagueIds = array_intersect($this->allLeagueIds, $selectedIds);
        } else {
            $leagueIds = $this->allLeagueIds;
        }

        $this->info("Загрузка предстоящих матчей с {$from} по {$to}");
        $this->info("Сезон: " . ($season) . "/" . ($season + 1));
        
        if ($skipOdds) {
            $this->warn("Пропускаем загрузку коэффициентов");
        }

        $totalMatches = 0;

        foreach ($leagueIds as $leagueId) {
            $this->info("Лига ID: {$leagueId}");
            
            try {
                $response = $this->api->getFixtures([
                    'league' => $leagueId,
                    'season' => $season,
                    'from'   => $from,
                    'to'     => $to,
                ], $timeout);

                if (!$response->successful()) {
                    $this->warn("  Ошибка HTTP {$response->status()}");
                    continue;
                }

                $data = $response->json();
                $fixtures = $data['response'] ?? [];
                $count = count($fixtures);

                if ($count > 0) {
                    $this->line("  Найдено {$count} матчей");
                } else {
                    $this->line("  Нет матчей");
                    continue;
                }

                foreach ($fixtures as $index => $matchData) {
                    $this->line("    [" . ($index + 1) . "/{$count}] " . 
                        ($matchData['teams']['home']['name'] ?? 'Unknown') . " vs " . 
                        ($matchData['teams']['away']['name'] ?? 'Unknown'));
                    
                    $fixture = $this->saveFixture($matchData);
                    $totalMatches++;

                    // Загружаем коэффициенты только если не пропускаем
                    if (!$skipOdds) {
                        $this->line("      Загрузка коэффициентов...");
                        $oddsCount = $this->fetchAndSavePrematchOdds($fixture, $matchData['fixture']['id']);
                        $this->line("      Загружено {$oddsCount} коэффициентов");
                    }
                    
                    // Пауза между матчами
                    usleep(100000);
                }
                
            } catch (\Exception $e) {
                $this->error("  Ошибка: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("✅ Загружено матчей: {$totalMatches}");
    }

    protected function saveFixture(array $data): Fixture
    {
        $fixtureInfo = $data['fixture'];
        $leagueData = $data['league'];
        $homeTeamData = $data['teams']['home'];
        $awayTeamData = $data['teams']['away'];

        $league = League::updateOrCreate(
            ['external_id' => $leagueData['id']],
            [
                'name'      => $leagueData['name'],
                'country'   => $leagueData['country'] ?? null,
                'logo_url'  => $leagueData['logo'] ?? null,
                'is_active' => true,
            ]
        );

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

        return Fixture::updateOrCreate(
            ['external_id' => $fixtureInfo['id']],
            [
                'league_id'    => $league->id,
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $awayTeam->id,
                'starting_at'  => Carbon::parse($fixtureInfo['date']),
                'status'       => $fixtureInfo['status']['short'] ?? 'NS',
                'home_score'   => null,
                'away_score'   => null,
            ]
        );
    }

    protected function fetchAndSavePrematchOdds(Fixture $fixture, int $apiFixtureId): int
    {
        try {
            $response = $this->api->getOdds($apiFixtureId, 10); // 10 секунд таймаут
            
            if (!$response->successful()) {
                return 0;
            }

            $data = $response->json();
            $bookmakersData = $data['response'][0]['bookmakers'] ?? [];
            
            $savedCount = 0;

            foreach ($bookmakersData as $bookmakerData) {
                if (!isset($bookmakerData['id'])) {
                    continue;
                }
                
                $bookmaker = \App\Models\Bookmaker::updateOrCreate(
                    ['external_id' => $bookmakerData['id']],
                    ['name' => $bookmakerData['name'] ?? 'Unknown']
                );

                foreach ($bookmakerData['bets'] ?? [] as $bet) {
                    if (($bet['name'] ?? '') !== 'Match Winner') {
                        continue;
                    }

                    foreach ($bet['values'] ?? [] as $outcome) {
                        $outcomeName = $outcome['value'] ?? '';
                        $oddValue    = $outcome['odd'] ?? 0;

                        $mapped = match ($outcomeName) {
                            'Home' => 'home',
                            'Draw' => 'draw',
                            'Away' => 'away',
                            default => null
                        };
                        
                        if (!$mapped || $oddValue == 0) {
                            continue;
                        }

                        \App\Models\Odd::updateOrCreate(
                            [
                                'fixture_id'   => $fixture->id,
                                'bookmaker_id' => $bookmaker->id,
                                'market'       => '1x2',
                                'outcome'      => $mapped,
                            ],
                            [
                                'value'      => $oddValue,
                                'fetched_at' => now(),
                            ]
                        );
                        $savedCount++;
                    }
                }
            }
            
            return $savedCount;
            
        } catch (\Exception $e) {
            return 0;
        }
    }
}