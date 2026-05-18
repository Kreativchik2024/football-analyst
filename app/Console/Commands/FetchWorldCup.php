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

class FetchWorldCup extends Command
{
    protected $signature = 'fetch:world-cup
                            {--years= : Годы чемпионатов через запятую (2018,2022,2026)}
                            {--skip-odds : пропустить загрузку коэффициентов}
                            {--skip-events : пропустить загрузку событий}
                            {--skip-statistics : пропустить загрузку статистики}
                            {--delay=250 : задержка между матчами в мс}
                            {--limit=7000 : лимит запросов}';

    protected $description = 'Загрузить все матчи и статистику чемпионатов мира (сборные)';

    protected ApiFootballService $api;

    // ID лиги чемпионата мира в API‑Football
    const WORLD_CUP_LEAGUE_ID = 1;

    // Диапазоны дат для каждого розыгрыша (чтобы не тянуть весь год)
    protected $worldCupRanges = [
        2018 => ['from' => '2018-06-14', 'to' => '2018-07-15'],
        2022 => ['from' => '2022-11-20', 'to' => '2022-12-18'],
        2026 => ['from' => '2026-06-11', 'to' => '2026-07-19'], // ориентировочно
    ];

    public function __construct(ApiFootballService $api)
    {
        parent::__construct();
        $this->api = $api;
    }

    public function handle()
    {
        $yearsOption = $this->option('years');
        if ($yearsOption) {
            $years = array_map('intval', explode(',', $yearsOption));
        } else {
            $years = array_keys($this->worldCupRanges);
        }

        $skipOdds = $this->option('skip-odds');
        $skipEvents = $this->option('skip-events');
        $skipStatistics = $this->option('skip-statistics');
        $delayMs = (int) $this->option('delay');
        $maxRequests = (int) $this->option('limit');

        $this->info("Загрузка данных о чемпионатах мира для лет: " . implode(', ', $years));

        $totalRequests = 0;
        $leagueId = self::WORLD_CUP_LEAGUE_ID;

        // Убедимся, что лига "World Cup" существует в БД
        $this->ensureWorldCupLeague();

        foreach ($years as $year) {
            if (!isset($this->worldCupRanges[$year])) {
                $this->warn("Диапазон дат для года {$year} не определён. Пропускаем.");
                continue;
            }

            $this->info("Обработка ЧМ-{$year}");

            $range = $this->worldCupRanges[$year];
            $from = Carbon::parse($range['from']);
            $to   = Carbon::parse($range['to']);

            $current = $from->copy();

            while ($current->lte($to)) {
                $date = $current->toDateString();

                $response = $this->api->getFixtures([
                    'league' => $leagueId,
                    'season' => $year,
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
                } else {
                    $current->addDay();
                    continue;
                }

                foreach ($fixtures as $matchData) {
                    $fixture = $this->saveFixture($matchData, $year);

                    if (!$skipStatistics) {
                        $this->fetchAndSaveStatistics($fixture, $matchData['fixture']['id']);
                        $totalRequests++;
                    }

                    if (!$skipOdds) {
                        $this->fetchAndSavePrematchOdds($fixture, $matchData['fixture']['id']);
                        $totalRequests++;
                    }

                    if (!$skipEvents) {
                        $this->fetchAndSaveEvents($fixture, $matchData['fixture']['id']);
                        $totalRequests++;
                    }

                    if ($totalRequests >= $maxRequests) {
                        $this->warn("Достигнут лимит запросов ({$maxRequests}). Продолжите позже.");
                        return 0;
                    }

                    if ($delayMs > 0) {
                        usleep($delayMs * 1000);
                    }
                }

                $current->addDay();
            }
        }

        $this->info("Готово. Всего запросов: {$totalRequests}");
        return 0;
    }

    /**
     * Убедиться, что лига "World Cup" есть в таблице leagues.
     */
    protected function ensureWorldCupLeague(): void
    {
        $league = League::where('external_id', self::WORLD_CUP_LEAGUE_ID)->first();
        if (!$league) {
            // Запросим данные о лиге через API
            $response = $this->api->getLeagues();
            if ($response->successful()) {
                $leagues = $response->json('response') ?? [];
                foreach ($leagues as $leagueData) {
                    if (($leagueData['league']['id'] ?? null) == self::WORLD_CUP_LEAGUE_ID) {
                        League::updateOrCreate(
                            ['external_id' => self::WORLD_CUP_LEAGUE_ID],
                            [
                                'name'      => $leagueData['league']['name'],
                                'country'   => $leagueData['country']['name'] ?? 'World',
                                'type'      => $leagueData['league']['type'] ?? 'cup',
                                'logo_url'  => $leagueData['league']['logo'] ?? null,
                                'is_active' => true,
                            ]
                        );
                        $this->info("Добавлена лига: Чемпионат мира");
                        break;
                    }
                }
            } else {
                // Если API не отвечает, создадим минимальную запись вручную
                League::updateOrCreate(
                    ['external_id' => self::WORLD_CUP_LEAGUE_ID],
                    [
                        'name'      => 'World Cup',
                        'country'   => 'International',
                        'type'      => 'cup',
                        'is_active' => true,
                    ]
                );
                $this->warn("Лига 'World Cup' создана вручную (без логотипа)");
            }
        }
    }

    /**
     * Сохранить матч (адаптировано для сборных).
     */
    protected function saveFixture(array $data, int $season): Fixture
    {
        $fixtureInfo = $data['fixture'] ?? null;
        if (!$fixtureInfo) throw new \Exception('No fixture data');

        $leagueData = $data['league'] ?? [];
        $homeTeamData = $data['teams']['home'] ?? [];
        $awayTeamData = $data['teams']['away'] ?? [];
        $goals = $data['goals'] ?? [];

        // Лига (должна уже существовать)
        $league = League::firstOrCreate(
            ['external_id' => self::WORLD_CUP_LEAGUE_ID],
            ['name' => 'World Cup', 'country' => 'International', 'type' => 'cup']
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

    // === Следующие три метода полностью копируют логику из FetchSeason ===
    protected function fetchAndSaveStatistics(Fixture $fixture, int $apiFixtureId): void
    {
        if ($fixture->home_xg !== null && $fixture->away_xg !== null && $fixture->matchStatistics()->count() > 0) {
            return;
        }

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
                $cleanValue = is_numeric($rawValue) ? (float) $rawValue : (is_string($rawValue) ? (float) rtrim($rawValue, '%') : null);

                if ($isHome) {
                    MatchStatistic::updateOrCreate(
                        ['fixture_id' => $fixture->id, 'stat_type' => $type],
                        ['home_value' => $cleanValue]
                    );
                    if ($type === 'expected_goals') $xgHome = $cleanValue;
                    if ($type === 'Ball Possession') $posHome = $cleanValue;
                } else {
                    MatchStatistic::updateOrCreate(
                        ['fixture_id' => $fixture->id, 'stat_type' => $type],
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
        if ($fixture->odds()->where('market', '1x2')->exists()) return;

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
        if ($fixture->matchEvents()->exists()) return;

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