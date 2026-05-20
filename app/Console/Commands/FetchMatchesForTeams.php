<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\Fixture;
use App\Services\ApiFootballService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class FetchMatchesForTeams extends Command
{
    protected $signature = 'fetch:matches-for-teams
                            {--teams= : ID команд через запятую (например 9,10,11)}
                            {--years= : годы через запятую (2018,2022,2024)}';

    protected $description = 'Загружает матчи для указанных команд за указанные годы';

    protected ApiFootballService $api;

    // Предустановленный список ID популярных сборных (можно расширить)
    private const DEFAULT_TEAMS = [9,10,11,12,13,14,15,16,17,18,19]; // Бразилия, Аргентина, Франция, Англия, Испания, Германия, Италия, Португалия, Нидерланды, Бельгия, Хорватия

    public function __construct(ApiFootballService $api)
    {
        parent::__construct();
        $this->api = $api;
    }

    public function handle()
    {
        $teamsOption = $this->option('teams');
        if ($teamsOption) {
            $teamIds = array_map('intval', explode(',', $teamsOption));
        } else {
            $teamIds = self::DEFAULT_TEAMS;
        }

        $yearsOption = $this->option('years');
        if ($yearsOption) {
            $years = array_map('intval', explode(',', $yearsOption));
        } else {
            $years = [2022]; // по умолчанию 2022
        }

        foreach ($teamIds as $teamId) {
            $this->info("Обработка команды ID: {$teamId}");
            $team = Team::firstOrCreate(['external_id' => $teamId], ['name' => "Team {$teamId}"]);

            foreach ($years as $year) {
                $this->line("  Год: {$year}");
                $page = 1;
                $hasMore = true;

                while ($hasMore) {
                    $response = $this->api->getFixtures([
                        'team' => $teamId,
                        'season' => $year,
                        'page' => $page,
                    ], 30);

                    if (!$response->successful()) {
                        $this->warn("    Ошибка HTTP: {$response->status()}");
                        break;
                    }

                    $data = $response->json();
                    $fixtures = $data['response'] ?? [];
                    $paging = $data['paging'] ?? [];
                    $totalPages = $paging['total'] ?? 1;

                    $this->line("    Страница {$page} из {$totalPages}, матчей: " . count($fixtures));

                    foreach ($fixtures as $matchData) {
                        $this->saveFixture($matchData);
                        usleep(50000);
                    }

                    $page++;
                    if ($page > $totalPages) $hasMore = false;
                }
            }
        }

        $this->info('✅ Готово');
        return 0;
    }

    private function saveFixture(array $data): void
    {
        $fixtureInfo = $data['fixture'];
        $leagueData = $data['league'] ?? [];
        $homeTeamData = $data['teams']['home'] ?? [];
        $awayTeamData = $data['teams']['away'] ?? [];
        $goals = $data['goals'] ?? [];

        $league = \App\Models\League::firstOrCreate(
            ['external_id' => $leagueData['id'] ?? 0],
            ['name' => $leagueData['name'] ?? 'International', 'country' => $leagueData['country'] ?? 'World', 'is_active' => true]
        );

        $homeTeam = \App\Models\Team::updateOrCreate(
            ['external_id' => $homeTeamData['id']],
            ['name' => $homeTeamData['name'], 'short_code' => $homeTeamData['code'] ?? null]
        );
        $awayTeam = \App\Models\Team::updateOrCreate(
            ['external_id' => $awayTeamData['id']],
            ['name' => $awayTeamData['name'], 'short_code' => $awayTeamData['code'] ?? null]
        );

        \App\Models\Fixture::updateOrCreate(
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