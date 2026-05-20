<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\Fixture;
use App\Services\ApiFootballService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class FetchNationalTeamsMatches extends Command
{
    protected $signature = 'fetch:national-teams-matches
                            {--force : Принудительно обновить существующие матчи}
                            {--limit=1000 : Максимум команд за раз}';

    protected $description = 'Загружает ВСЕ матчи (включая товарищеские) для всех национальных сборных';

    protected ApiFootballService $api;

    public function __construct(ApiFootballService $api)
    {
        parent::__construct();
        $this->api = $api;
    }

    public function handle()
    {
        $force = $this->option('force');
        $limit = (int) $this->option('limit');
        $this->info('🚀 Загружаем матчи национальных сборных...');

        // Получаем список всех команд (с пагинацией)
        $page = 1;
        $allTeams = [];
        do {
            $response = $this->api->getTeams(['page' => $page]);
            if (!$response->successful()) {
                $this->error("Не удалось загрузить команды, страница $page");
                return 1;
            }
            $data = $response->json();
            $teamsPage = $data['response'] ?? [];
            $allTeams = array_merge($allTeams, $teamsPage);
            $paging = $data['paging'] ?? [];
            $totalPages = $paging['total'] ?? 1;
            $page++;
        } while ($page <= $totalPages);

        // Фильтруем только национальные сборные
        $nationalTeams = array_filter($allTeams, fn($t) => ($t['team']['national'] ?? false) === true);
        $this->info("Найдено национальных сборных: " . count($nationalTeams));

        $bar = $this->output->createProgressBar(count($nationalTeams));
        $bar->start();

        foreach ($nationalTeams as $teamData) {
            $team = Team::updateOrCreate(
                ['external_id' => $teamData['team']['id']],
                [
                    'name'       => $teamData['team']['name'],
                    'country'    => $teamData['country']['name'] ?? null,
                    'logo_url'   => $teamData['team']['logo'] ?? null,
                    'short_code' => $teamData['team']['code'] ?? null,
                ]
            );

            // Загружаем матчи команды
            $pageFixtures = 1;
            $hasMore = true;
            while ($hasMore) {
                $response = $this->api->getFixtures([
                    'team' => $team->external_id,
                    'page' => $pageFixtures,
                ]);
                if (!$response->successful()) {
                    $this->warn("Ошибка загрузки матчей для команды {$team->name}");
                    break;
                }
                $data = $response->json();
                $fixtures = $data['response'] ?? [];
                $paging = $data['paging'] ?? [];
                $totalPages = $paging['total'] ?? 1;

                foreach ($fixtures as $matchData) {
                    $this->saveFixture($matchData);
                    usleep(20000);
                }

                $pageFixtures++;
                if ($pageFixtures > $totalPages) $hasMore = false;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->info("\n✅ Готово!");
        return 0;
    }

    protected function saveFixture(array $data): void
    {
        $fixtureInfo = $data['fixture'];
        $leagueData = $data['league'] ?? [];
        $homeTeamData = $data['teams']['home'] ?? [];
        $awayTeamData = $data['teams']['away'] ?? [];
        $goals = $data['goals'] ?? [];

        // Лига (может отсутствовать – тогда пропускаем, но для целостности создаём фиктивную)
        $league = \App\Models\League::firstOrCreate(
            ['external_id' => $leagueData['id'] ?? 0],
            [
                'name'      => $leagueData['name'] ?? 'Friendly',
                'country'   => $leagueData['country'] ?? 'World',
                'type'      => $leagueData['type'] ?? 'Friendly',
                'is_active' => true,
            ]
        );

        $homeTeam = \App\Models\Team::updateOrCreate(
            ['external_id' => $homeTeamData['id']],
            ['name' => $homeTeamData['name'], 'short_code' => $homeTeamData['code'] ?? null, 'logo_url' => $homeTeamData['logo'] ?? null]
        );
        $awayTeam = \App\Models\Team::updateOrCreate(
            ['external_id' => $awayTeamData['id']],
            ['name' => $awayTeamData['name'], 'short_code' => $awayTeamData['code'] ?? null, 'logo_url' => $awayTeamData['logo'] ?? null]
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