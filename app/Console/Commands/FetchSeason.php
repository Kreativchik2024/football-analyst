<?php

namespace App\Console\Commands;

use App\Models\League;
use App\Models\Team;
use App\Models\Fixture;
use App\Models\MatchStatistic;
use App\Models\MatchEvent;
use App\Models\Bookmaker;
use App\Models\Odd;
use App\Services\ApiFootballService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class FetchSeason extends Command
{
    protected $signature = 'fetch:season
                            {season : год начала сезона (2022, 2023, 2024, 2025)}
                            {--from= : дата начала (Y-m-d)}
                            {--to= : дата конца (Y-m-d)}
                            {--leagues= : список ID лиг через запятую}';

    protected $description = 'Загрузить все матчи и полную статистику для указанного сезона по выбранным лигам (по дням)';

    protected ApiFootballService $api;

    protected array $allLeagueIds = [
        39, 40, 140, 141, 135, 136, 78, 79, 61, 62,
        94, 95, 71, 72, 235, 236, 19, 20, 294, 295, 253, 254,
        1, 2, 3, 4, 13
    ];

    public function __construct(ApiFootballService $api)
    {
        parent::__construct();
        $this->api = $api;
    }

    public function handle()
    {
        $season = (int) $this->argument('season');
        if (!in_array($season, [2022, 2023, 2024, 2025])) {
            $this->error('Сезон должен быть 2022, 2023, 2024 или 2025');
            return;
        }

        $leaguesOption = $this->option('leagues');
        if ($leaguesOption) {
            $selectedIds = array_map('intval', explode(',', $leaguesOption));
            $leagueIds = array_intersect($this->allLeagueIds, $selectedIds);
        } else {
            $leagueIds = $this->allLeagueIds;
        }

        if (empty($leagueIds)) {
            $this->error('Не выбрано ни одной лиги для загрузки.');
            return;
        }

        $fromDate = $this->option('from');
        $toDate   = $this->option('to');

        if (!$fromDate && !$toDate && $season == 2022) {
            $fromDate = '2023-04-01';
            $toDate   = '2023-07-31';
        }

        $this->info("Загрузка сезона {$season}/".($season+1)." для лиг: " . implode(',', $leagueIds));
        $this->fetchLeagues($leagueIds);

        $totalRequests = 0;

        foreach ($leagueIds as $leagueId) {
            $this->info("Лига ID: {$leagueId}");

            $range = $this->getRange($leagueId, $season, $fromDate, $toDate);
            if (!$range) {
                $this->warn("  Нет информации о сезоне, пропускаем");
                continue;
            }

            $current = Carbon::parse($range['from']);
            $end     = Carbon::parse($range['to']);

            while ($current->lte($end)) {
                $date = $current->toDateString();

                $response = $this->api->getFixtures([
                    'league' => $leagueId,
                    'season' => $season,
                    'from'   => $date,
                    'to'     => $date,
                ]);
                $totalRequests++;

                if (!$response->successful()) {
                    $this->warn("  Ошибка HTTP {$response->status()} за {$date}");
                    $current->addDay();
                    continue;
                }

                $data = $response->json();
                $fixtures = $data['response'] ?? [];
                $count = count($fixtures);
                if ($count > 0) {
                    $this->line("  {$date}: {$count} матчей");
                }

                foreach ($fixtures as $matchData) {
                    $fixture = $this->saveFixture($matchData);

                    $this->fetchAndSaveStatistics($fixture, $matchData['fixture']['id']);
                    $totalRequests++;

                    $this->fetchAndSavePrematchOdds($fixture, $matchData['fixture']['id']);
                    $totalRequests++;

                    $this->fetchAndSaveEvents($fixture, $matchData['fixture']['id']);
                    $totalRequests++;

                    if ($totalRequests >= 7000) {
                        $this->warn("Достигнут лимит запросов (7000). Продолжите завтра.");
                        break 3;
                    }

                    usleep(250000);
                }

                $current->addDay();
            }

            if ($totalRequests >= 7000) break;
        }

        $this->info("Готово. Всего запросов: {$totalRequests}");
    }

    protected function fetchLeagues(array $leagueIds)
    {
        $existing = League::whereIntegerInRaw('external_id', $leagueIds)->count();
        if ($existing < count($leagueIds)) {
            $response = $this->api->getLeagues();
            if ($response->successful()) {
                $leagues = $response->json('response') ?? [];
                foreach ($leagues as $data) {
                    $id = $data['league']['id'] ?? null;
                    if ($id && in_array($id, $leagueIds)) {
                        League::updateOrCreate(
                            ['external_id' => $id],
                            [
                                'name'      => $data['league']['name'],
                                'country'   => $data['country']['name'] ?? null,
                                'type'      => $data['league']['type'] ?? 'league',
                                'logo_url'  => $data['league']['logo'] ?? null,
                                'is_active' => true,
                            ]
                        );
                    }
                }
            }
        }
    }

    protected function getRange(int $leagueId, int $season, ?string $from, ?string $to): ?array
    {
        if ($from && $to) {
            return ['from' => $from, 'to' => $to];
        }

        $specialRanges = [
            1 => [2022 => ['from'=>'2022-11-20','to'=>'2022-12-18']],
            2 => [2022 => ['from'=>'2022-09-06','to'=>'2023-06-10'],
                  2023 => ['from'=>'2023-09-19','to'=>'2024-06-01'],
                  2024 => ['from'=>'2024-09-17','to'=>'2025-06-07'],
                  2025 => ['from'=>'2025-09-16','to'=>'2026-06-06']],
            3 => [2022 => ['from'=>'2022-09-08','to'=>'2023-05-31'],
                  2023 => ['from'=>'2023-09-21','to'=>'2024-05-22'],
                  2024 => ['from'=>'2024-09-25','to'=>'2025-05-21'],
                  2025 => ['from'=>'2025-09-24','to'=>'2026-05-20']],
            4 => [2024 => ['from'=>'2024-06-14','to'=>'2024-07-14']],
            13=> [2022 => ['from'=>'2022-04-05','to'=>'2022-10-29'],
                  2023 => ['from'=>'2023-04-04','to'=>'2023-10-28'],
                  2024 => ['from'=>'2024-04-02','to'=>'2024-10-26'],
                  2025 => ['from'=>'2025-04-01','to'=>'2025-10-25']],
        ];

        if (isset($specialRanges[$leagueId][$season])) {
            return $specialRanges[$leagueId][$season];
        }

        if ($season >= 2023) {
            return [
                'from' => "{$season}-08-01",
                'to'   => ($season+1)."-07-31",
            ];
        }

        return ['from' => '2023-04-01', 'to' => '2023-07-31'];
    }

    protected function saveFixture(array $data): Fixture
    {
        $fixtureInfo = $data['fixture'] ?? null;
        if (!$fixtureInfo) throw new \Exception('No fixture data');

        $leagueData = $data['league'] ?? [];
        $homeTeamData = $data['teams']['home'] ?? [];
        $awayTeamData = $data['teams']['away'] ?? [];
        $goals = $data['goals'] ?? [];

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
                'starting_at'  => $fixtureInfo['date'],
                'status'       => $fixtureInfo['status']['short'] ?? 'NS',
                'home_score'   => $goals['home'] ?? null,
                'away_score'   => $goals['away'] ?? null,
            ]
        );
    }

    protected function fetchAndSaveStatistics(Fixture $fixture, int $apiFixtureId): void
    {
        $response = $this->api->getFixtureStatistics($apiFixtureId);
        if (!$response->successful()) return;

        $teamsStats = $response->json('response') ?? [];

        $xgHome = null; $xgAway = null;
        $posHome = null; $posAway = null;

        foreach ($teamsStats as $teamStats) {
            $teamId = $teamStats['team']['id'] ?? null;
            if (!$teamId) continue;

            $isHome = $teamId == $fixture->homeTeam?->external_id;

            foreach ($teamStats['statistics'] as $stat) {
                $type = $stat['type'] ?? 'unknown';
                $rawValue = $stat['value'] ?? null;

                // Очищаем значение: убираем % и преобразуем в число
                $cleanValue = is_numeric($rawValue) ? (float) $rawValue : (is_string($rawValue) ? (float) rtrim($rawValue, '%') : null);

                if ($isHome) {
                    MatchStatistic::updateOrCreate(
                        ['fixture_id' => $fixture->id, 'stat_type'  => $type],
                        ['home_value' => $cleanValue]
                    );
                    if ($type === 'expected_goals') $xgHome = $cleanValue;
                    if ($type === 'Ball Possession') $posHome = $cleanValue;
                } else {
                    MatchStatistic::updateOrCreate(
                        ['fixture_id' => $fixture->id, 'stat_type'  => $type],
                        ['away_value' => $cleanValue]
                    );
                    if ($type === 'expected_goals') $xgAway = $cleanValue;
                    if ($type === 'Ball Possession') $posAway = $cleanValue;
                }
            }
        }

        $fixture->update([
            'home_xg' => $xgHome,
            'away_xg' => $xgAway,
            'home_possession' => $posHome,
            'away_possession' => $posAway,
        ]);
    }

    protected function fetchAndSavePrematchOdds(Fixture $fixture, int $apiFixtureId): void
    {
        $response = $this->api->getOdds($apiFixtureId);
        if (!$response->successful()) return;

        $data = $response->json('response')[0] ?? [];
        $bookmakersData = $data['bookmakers'] ?? [];

        foreach ($bookmakersData as $bookmakerData) {
            $bookmaker = Bookmaker::updateOrCreate(
                ['external_id' => $bookmakerData['id']],
                ['name' => $bookmakerData['name']]
            );

            foreach ($bookmakerData['bets'] ?? [] as $bet) {
                if (($bet['name'] ?? '') !== 'Match Winner') continue;

                foreach ($bet['values'] ?? [] as $outcome) {
                    $outcomeName = $outcome['value'] ?? '';
                    $oddValue    = $outcome['odd'] ?? 0;

                    $mapped = match ($outcomeName) {
                        'Home' => 'home',
                        'Draw' => 'draw',
                        'Away' => 'away',
                        default => null
                    };
                    if (!$mapped) continue;

                    Odd::updateOrCreate(
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
                }
            }
        }
    }

    protected function fetchAndSaveEvents(Fixture $fixture, int $apiFixtureId): void
    {
        $response = $this->api->getFixtureEvents($apiFixtureId);
        if (!$response->successful()) return;

        $events = $response->json('response') ?? [];

        foreach ($events as $event) {
            MatchEvent::updateOrCreate(
                [
                    'fixture_id'  => $fixture->id,
                    'elapsed'     => $event['time']['elapsed'] ?? 0,
                    'event_type'  => $event['type'] ?? 'unknown',
                    'player_name' => $event['player']['name'] ?? null,
                ],
                [
                    'detail'      => $event['detail'] ?? '',
                    'assist_name' => $event['assist']['name'] ?? null,
                    'team_type'   => (isset($event['team']['id']) && $event['team']['id'] == $fixture->homeTeam?->external_id) ? 'home' : 'away',
                ]
            );
        }
    }
}