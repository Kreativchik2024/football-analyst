<?php

namespace App\Console\Commands;

use App\Models\Injury;
use App\Models\Team;
use App\Services\ApiFootballService;
use Illuminate\Console\Command;

class FetchInjuries extends Command
{
    protected $signature = 'fetch:injuries {--league= : ID лиги}';
    protected $description = 'Загрузить текущие травмы игроков из API-Football';

    public function handle(ApiFootballService $api)
    {
        $leagueId = $this->option('league') ?: null;

        $params = [];
        if ($leagueId) {
            $params['league'] = $leagueId;
        }

        $response = $api->getInjuries($params);
        if (!$response->successful()) {
            $this->error('Ошибка загрузки травм: ' . $response->status());
            return;
        }

        $injuries = $response->json('response') ?? [];
        $savedCount = 0;

        foreach ($injuries as $data) {
            $team = Team::firstOrCreate(
                ['external_id' => $data['team']['id']],
                [
                    'name'       => $data['team']['name'],
                    'logo_url'   => $data['team']['logo'] ?? null,
                    'short_code' => $data['team']['code'] ?? null,
                ]
            );

            Injury::updateOrCreate(
                ['external_id' => $data['id']],
                [
                    'team_id'     => $team->id,
                    'player_name' => $data['player']['name'] ?? 'Неизвестный игрок',
                    'reason'      => $data['reason'] ?? null,
                    'start_date'  => $data['start_date'] ?? null,
                    'end_date'    => $data['end_date'] ?? null,
                ]
            );

            $savedCount++;
        }

        $this->info("Загружено травм: {$savedCount}");
    }
}