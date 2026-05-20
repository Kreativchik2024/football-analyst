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

class FetchWorldQualifiers extends Command
{
    protected $signature = 'fetch:world-qualifiers
                            {--years= : Годы (сезоны) квалификации через запятую (2018,2022,2026)}
                            {--confederations= : Конфедерации через запятую (uefa,caf,afc,conmebol,concacaf,ofc) – все по умолчанию}
                            {--skip-odds : пропустить загрузку коэффициентов}
                            {--skip-events : пропустить загрузку событий}
                            {--skip-statistics : пропустить загрузку статистики}
                            {--delay=250 : задержка между матчами в мс}
                            {--limit=7000 : лимит запросов}';

    protected $description = 'Загружает матчи отборочных турниров к чемпионату мира для указанных конфедераций и годов';

    protected ApiFootballService $api;

    // ID лиг отборочных турниров
    private const QUALIFIER_LEAGUES = [
        'uefa' => 2,
        'caf'  => 3,
        'afc'  => 4,
        'conmebol' => 5,
        'concacaf' => 6,
        'ofc'  => 7,
    ];

    // Диапазоны дат для квалификации (можно расширить, если нужно)
    // По умолчанию загружаем весь год, но можно уточнить
    private function getQualifierRange(int $year): array
    {
        return [
            'from' => "{$year}-01-01",
            'to'   => "{$year}-12-31",
        ];
    }

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
            // По умолчанию последние несколько лет, включая 2025 (квалификация к ЧМ-2026)
            $years = [2021, 2022, 2023, 2024, 2025];
        }

        $confederationsOption = $this->option('confederations');
        if ($confederationsOption) {
            $confeds = array_map('trim', explode(',', $confederationsOption));
            $leaguesToLoad = array_intersect_key(self::QUALIFIER_LEAGUES, array_flip($confeds));
        } else {
            $leaguesToLoad = self::QUALIFIER_LEAGUES;
        }

        $skipOdds = $this->option('skip-odds');
        $skipEvents = $this->option('skip-events');
        $skipStatistics = $this->option('skip-statistics');
        $delayMs = (int) $this->option('delay');
        $maxRequests = (int) $this->option('limit');

        $this->info("Загрузка отборочных матчей чемпионата мира");
        $this->info("Годы: " . implode(', ', $years));
        $this->info("Конфедерации: " . implode(', ', array_keys($leaguesToLoad)));

        $totalRequests = 0;

        foreach ($years as $year) {
            $range = $this->getQualifierRange($year);
            $from = Carbon::parse($range['from']);
            $to   = Carbon::parse($range['to']);

            foreach ($leaguesToLoad as $conf => $leagueId) {
                $this->info("Обработка: {$conf} (ID {$leagueId}), сезон {$year}");

                // Убедимся, что лига существует в БД
                $league = $this->ensureQualifierLeague($leagueId, $conf);

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
                        $fixture = $this->saveFixture($matchData, $league->id, $year);
                        // Загружаем статистику, события, коэффициенты по желанию
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
        }

        $this->info("Готово. Всего запросов: {$totalRequests}");
        return 0;
    }

    protected function ensureQualifierLeague(int $leagueId, string $conf): League
    {
        $league = League::where('external_id', $leagueId)->first();
        if (!$league) {
            $response = $this->api->getLeagues();
            if ($response->successful()) {
                $leagues = $response->json('response') ?? [];
                foreach ($leagues as $leagueData) {
                    if (($leagueData['league']['id'] ?? null) == $leagueId) {
                        $league = League::updateOrCreate(
                            ['external_id' => $leagueId],
                            [
                                'name'      => $leagueData['league']['name'],
                                'country'   => $leagueData['country']['name'] ?? 'International',
                                'type'      => $leagueData['league']['type'] ?? 'Cup',
                                'logo_url'  => $leagueData['league']['logo'] ?? null,
                                'is_active' => true,
                            ]
                        );
                        $this->info("Добавлена лига: {$leagueData['league']['name']}");
                        break;
                    }
                }
            }
            if (!$league) {
                $league = League::create([
                    'external_id' => $leagueId,
                    'name'        => "World Cup Qualifying - {$conf}",
                    'country'     => 'International',
                    'type'        => 'Cup',
                    'is_active'   => true,
                ]);
                $this->warn("Лига квалификации для {$conf} создана вручную (ID {$leagueId})");
            }
        }
        return $league;
    }

    protected function saveFixture(array $data, int $leagueId, int $season): Fixture
    {
        $fixtureInfo = $data['fixture'];
        $homeTeamData = $data['teams']['home'] ?? [];
        $awayTeamData = $data['teams']['away'] ?? [];
        $goals = $data['goals'] ?? [];

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
                'league_id'    => $leagueId,
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $awayTeam->id,
                'starting_at'  => Carbon::parse($fixtureInfo['date']),
                'status'       => $fixtureInfo['status']['short'] ?? 'NS',
                'home_score'   => $goals['home'] ?? null,
                'away_score'   => $goals['away'] ?? null,
            ]
        );
    }

    // Методы fetchAndSaveStatistics, fetchAndSavePrematchOdds, fetchAndSaveEvents можно скопировать из FetchWorldCup
    // (они идентичны, поэтому я их повторять не буду, но вы можете вставить те же самые реализации)
}