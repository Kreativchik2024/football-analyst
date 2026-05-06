<?php

namespace App\Console\Commands;

use App\Models\Fixture;
use App\Models\MatchStatistic;
use App\Models\Bookmaker;
use App\Models\Odd;
use App\Models\MatchEvent;
use App\Services\ApiFootballService;
use Illuminate\Console\Command;

class FetchMissingDetails extends Command
{
    protected $signature = 'fetch:missing-details {--limit=100 : Максимальное число матчей для обработки}';
    protected $description = 'Дозагружает статистику, коэффициенты и события для матчей, у которых их нет';

    protected ApiFootballService $api;

    public function __construct(ApiFootballService $api)
    {
        parent::__construct();
        $this->api = $api;
    }

    public function handle()
    {
        $limit = (int) $this->option('limit');

        $fixtures = Fixture::whereIn('status', ['FT', 'AET', 'PEN'])
            ->where(function ($q) {
                $q->whereDoesntHave('matchStatistics')
                  ->orWhereDoesntHave('odds')
                  ->orWhereDoesntHave('matchEvents');
            })
            ->limit($limit)
            ->get();

        $this->info("Обрабатываем {$fixtures->count()} матчей...");

        foreach ($fixtures as $fixture) {
            $this->line("Матч ID {$fixture->id}: {$fixture->homeTeam->name} vs {$fixture->awayTeam->name}");

            // 1. Статистика
            if ($fixture->matchStatistics->isEmpty()) {
                $response = $this->api->getFixtureStatistics($fixture->external_id);
                if ($response->successful()) {
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
                    $this->info("  ✓ Статистика загружена");
                }
            }

            // 2. Коэффициенты
            // Загружаем коэффициенты, только если нет 1x2
if ($fixture->odds()->where('market', '1x2')->doesntExist()) {
                $response = $this->api->getOdds($fixture->external_id);
                if ($response->successful()) {
                    $data = $response->json('response')[0] ?? [];
                    foreach ($data['bookmakers'] ?? [] as $bookmakerData) {
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
                                    ['fixture_id' => $fixture->id, 'bookmaker_id' => $bookmaker->id, 'market' => '1x2', 'outcome' => $mapped],
                                    ['value' => $oddValue, 'fetched_at' => now()]
                                );
                            }
                        }
                    }
                    $this->info("  ✓ Коэффициенты загружены");
                }
            }

            // 3. События
            if ($fixture->matchEvents->isEmpty()) {
                $response = $this->api->getFixtureEvents($fixture->external_id);
                if ($response->successful()) {
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
                    $this->info("  ✓ События загружены");
                }
            }
        }

        $this->info("Готово.");
    }
}