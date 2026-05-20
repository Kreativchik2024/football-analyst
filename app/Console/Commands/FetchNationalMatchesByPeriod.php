<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\Fixture;
use App\Models\League;
use App\Services\ApiFootballService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class FetchNationalMatchesByPeriod extends Command
{
    protected $signature = 'fetch:national-matches-by-period
                            {--from= : дата начала (Y-m-d)}
                            {--to= : дата конца (Y-m-d)}
                            {--year= : конкретный год (например 2022)}
                            {--years= : список лет через запятую}
                            {--limit=100 : максимум матчей на страницу}
                            {--delay=100 : задержка в мс между запросами}
                            {--force : перезаписывать существующие матчи}';

    protected $description = 'Загружает матчи НАЦИОНАЛЬНЫХ СБОРНЫХ (автоопределение) за указанный период';

    protected ApiFootballService $api;

    public function __construct(ApiFootballService $api)
    {
        parent::__construct();
        $this->api = $api;
    }

    public function handle()
    {
        $periods = $this->buildPeriods();
        if (empty($periods)) {
            $this->error('Не указан период. Используйте --from/--to, --year или --years');
            return 1;
        }

        $limit = (int) $this->option('limit');
        $delay = (int) $this->option('delay');

        foreach ($periods as $period) {
            $from = $period['from'];
            $to = $period['to'];
            $this->info("Обработка периода: {$from} — {$to}");

            $page = 1;
            $hasMore = true;
            $totalMatches = 0;
            $savedMatches = 0;

            while ($hasMore) {
                $response = $this->api->getFixtures([
                    'from' => $from,
                    'to'   => $to,
                    'page' => $page,
                ], 30);

                if (!$response->successful()) {
                    $this->error("Ошибка API: " . $response->status());
                    break;
                }

                $data = $response->json();
                $fixtures = $data['response'] ?? [];
                $paging = $data['paging'] ?? [];
                $totalPages = $paging['total'] ?? 1;

                $this->line("Страница {$page} из {$totalPages}, получено матчей: " . count($fixtures));
                $totalMatches += count($fixtures);

                foreach ($fixtures as $matchData) {
                    // Определяем, являются ли команды национальными сборными
                    $homeTeamData = $matchData['teams']['home'] ?? [];
                    $awayTeamData = $matchData['teams']['away'] ?? [];
                    $isHomeNational = $homeTeamData['national'] ?? false;
                    $isAwayNational = $awayTeamData['national'] ?? false;

                    // Если хотя бы одна команда — национальная сборная, сохраняем матч
                    if ($isHomeNational || $isAwayNational) {
                        $this->saveFixture($matchData);
                        $savedMatches++;
                    }
                }

                $page++;
                if ($page > $totalPages) $hasMore = false;
                usleep($delay * 1000);
            }

            $this->line("Всего матчей в периоде: {$totalMatches}, сохранено национальных: {$savedMatches}");
        }

        $this->info('✅ Готово');
        return 0;
    }

    private function buildPeriods(): array
    {
        $periods = [];

        if ($this->option('from') && $this->option('to')) {
            $periods[] = [
                'from' => Carbon::parse($this->option('from'))->toDateString(),
                'to'   => Carbon::parse($this->option('to'))->toDateString(),
            ];
            return $periods;
        }

        if ($this->option('year')) {
            $year = (int) $this->option('year');
            $periods[] = [
                'from' => "{$year}-01-01",
                'to'   => "{$year}-12-31",
            ];
            return $periods;
        }

        if ($this->option('years')) {
            $years = explode(',', $this->option('years'));
            foreach ($years as $year) {
                $year = trim($year);
                $periods[] = [
                    'from' => "{$year}-01-01",
                    'to'   => "{$year}-12-31",
                ];
            }
            return $periods;
        }

        return [];
    }

    private function saveFixture(array $data): void
    {
        $fixtureInfo = $data['fixture'];
        $leagueData = $data['league'] ?? [];
        $homeTeamData = $data['teams']['home'] ?? [];
        $awayTeamData = $data['teams']['away'] ?? [];
        $goals = $data['goals'] ?? [];

        // Лига (если нет данных, создаём фиктивную)
        $league = League::firstOrCreate(
            ['external_id' => $leagueData['id'] ?? 0],
            [
                'name'      => $leagueData['name'] ?? 'International',
                'country'   => $leagueData['country'] ?? 'World',
                'type'      => $leagueData['type'] ?? 'Friendly',
                'is_active' => true,
            ]
        );

        // Хозяева
        $homeTeam = Team::updateOrCreate(
            ['external_id' => $homeTeamData['id']],
            [
                'name'       => $homeTeamData['name'],
                'country'    => $homeTeamData['country'] ?? null,
                'logo_url'   => $homeTeamData['logo'] ?? null,
                'short_code' => $homeTeamData['code'] ?? null,
            ]
        );

        // Гости
        $awayTeam = Team::updateOrCreate(
            ['external_id' => $awayTeamData['id']],
            [
                'name'       => $awayTeamData['name'],
                'country'    => $awayTeamData['country'] ?? null,
                'logo_url'   => $awayTeamData['logo'] ?? null,
                'short_code' => $awayTeamData['code'] ?? null,
            ]
        );

        Fixture::updateOrCreate(
            ['external_id' => $fixtureInfo['id']],
            [
                'league_id'    => $league->id,
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $awayTeam->id,
                'starting_at'  => Carbon::parse($fixtureInfo['date']),
                'status'       => $fixtureInfo['status']['short'],
                'home_score'   => $goals['home'] ?? null,
                'away_score'   => $goals['away'] ?? null,
            ]
        );
    }
}