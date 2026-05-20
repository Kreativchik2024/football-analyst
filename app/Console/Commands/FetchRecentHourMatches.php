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

class FetchRecentHourMatches extends Command
{
    protected $signature = 'matches:recent-hour
                            {--skip-odds : пропустить загрузку коэффициентов}
                            {--skip-events : пропустить загрузку событий}
                            {--skip-statistics : пропустить загрузку статистики}
                            {--delay=250 : задержка между матчами в мс}';

    protected $description = 'Загрузить матчи, которые стартуют в ближайший час (плюс уже начавшиеся недавно)';

    protected ApiFootballService $api;

    // ID лиг для сканирования (можно сузить, если нужно)
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
        $skipOdds = $this->option('skip-odds');
        $skipEvents = $this->option('skip-events');
        $skipStatistics = $this->option('skip-statistics');
        $delayMs = (int) $this->option('delay');

        $now = Carbon::now();
        $today = $now->toDateString();

        $this->info("Поиск матчей, стартующих в ближайший час (с {$now->toDateTimeString()})");

        $totalUpdated = 0;
        $totalRequests = 0;

        foreach ($this->allLeagueIds as $leagueId) {
            $season = $now->month >= 8 ? $now->year : $now->year - 1;
            if ($season < 2022) $season = 2022;

            $response = $this->api->getFixtures([
                'league' => $leagueId,
                'season' => $season,
                'date'   => $today,
            ]);
            $totalRequests++;

            if (!$response->successful()) {
                $this->warn("Лига {$leagueId}: ошибка HTTP {$response->status()}");
                continue;
            }

            $data = $response->json();
            $fixtures = $data['response'] ?? [];
            if (empty($fixtures)) {
                continue;
            }

            // Фильтр по времени: матчи, начавшиеся не более 5 минут назад или стартующие в течение 60 минут
            $recentFixtures = array_filter($fixtures, function ($matchData) use ($now) {
                $kickoff = Carbon::parse($matchData['fixture']['date']);
                $diffMinutes = $kickoff->diffInMinutes($now, false);
                return $diffMinutes >= -5 && $diffMinutes <= 60;
            });

            if (empty($recentFixtures)) {
                continue;
            }

            $this->info("Лига {$leagueId}: найдено " . count($recentFixtures) . " матчей за последний час");

            foreach ($recentFixtures as $matchData) {
                $fixture = $this->saveFixture($matchData);
                $kickoffTime = Carbon::parse($matchData['fixture']['date'])->format('H:i');

                if (!$skipStatistics) {
                    $this->fetchAndSaveStatistics($fixture, $matchData['fixture']['id']);
                    $totalRequests++;
                    usleep($delayMs * 1000);
                }

                if (!$skipOdds) {
                    $this->fetchAndSavePrematchOdds($fixture, $matchData['fixture']['id']);
                    $totalRequests++;
                    usleep($delayMs * 1000);
                }

                if (!$skipEvents) {
                    $this->fetchAndSaveEvents($fixture, $matchData['fixture']['id']);
                    $totalRequests++;
                    usleep($delayMs * 1000);
                }

                $totalUpdated++;
                $this->line("  Обновлён матч: {$fixture->homeTeam->name} vs {$fixture->awayTeam->name} ({$kickoffTime})");
            }
        }

        $this->info("Готово. Обновлено матчей: {$totalUpdated}, запросов: {$totalRequests}");
        return 0;
    }

    /**
     * Сохранить матч (полная копия из FetchSeason)
     */
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

    /**
     * Загрузить и сохранить статистику матча
     */
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

    /**
     * Загрузить и сохранить коэффициенты (pre-match)
     */
    protected function fetchAndSavePrematchOdds(Fixture $fixture, int $apiFixtureId): void
    {
        if ($fixture->odds()->where('market', '1x2')->exists()) {
            return;
        }

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

    /**
     * Загрузить и сохранить события матча (голы, карточки и т.д.)
     */
    protected function fetchAndSaveEvents(Fixture $fixture, int $apiFixtureId): void
    {
        if ($fixture->matchEvents()->exists()) {
            return;
        }

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