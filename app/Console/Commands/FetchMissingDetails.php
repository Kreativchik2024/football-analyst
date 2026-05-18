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
    protected $signature = 'fetch:missing-details 
                            {--limit=100 : Максимальное число матчей для обработки}
                            {--from-id= : Начать с определённого ID матча}
                            {--fixture-id= : Обработать конкретный ID матча}';
    
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
        $fromId = (int) $this->option('from-id');
        $specificFixture = (int) $this->option('fixture-id');
        
        $query = Fixture::whereIn('status', ['FT', 'AET', 'PEN'])
            ->where(function ($q) {
                $q->whereDoesntHave('matchStatistics')
                  ->orWhereDoesntHave('odds')
                  ->orWhereDoesntHave('matchEvents');
            });
        
        // Если указан конкретный ID матча
        if ($specificFixture) {
            $query->where('id', $specificFixture);
            $this->info("Обрабатываем только матч ID: {$specificFixture}");
        }
        // Если указан начальный ID
        elseif ($fromId) {
            $query->where('id', '>=', $fromId);
            $this->info("Начинаем с ID матча: {$fromId}");
        }
        
        $fixtures = $query->orderBy('id')->limit($limit)->get();

        $this->info("Обрабатываем {$fixtures->count()} матчей...");

        foreach ($fixtures as $fixture) {
            $this->line("Матч ID {$fixture->id}: {$fixture->homeTeam->name} vs {$fixture->awayTeam->name}");

            // 1. Статистика
            if ($fixture->matchStatistics->isEmpty()) {
                try {
                    $response = $this->api->getFixtureStatistics($fixture->external_id);
                    if ($response->successful()) {
                        // ... ваш существующий код сохранения статистики ...
                        $this->info("  ✓ Статистика загружена");
                    } else {
                        $this->warn("  ✗ Ошибка статистики: HTTP {$response->status()}");
                    }
                } catch (\Exception $e) {
                    $this->error("  ✗ Ошибка: " . $e->getMessage());
                }
            }

            // 2. Коэффициенты
            if ($fixture->odds()->where('market', '1x2')->doesntExist()) {
                try {
                    $response = $this->api->getOdds($fixture->external_id);
                    if ($response->successful()) {
                        // ... ваш существующий код сохранения коэффициентов ...
                        $this->info("  ✓ Коэффициенты загружены");
                    }
                } catch (\Exception $e) {
                    $this->error("  ✗ Ошибка коэффициентов: " . $e->getMessage());
                }
            }

            // 3. События
            if ($fixture->matchEvents->isEmpty()) {
                try {
                    $response = $this->api->getFixtureEvents($fixture->external_id);
                    if ($response->successful()) {
                        // ... ваш существующий код сохранения событий ...
                        $this->info("  ✓ События загружены");
                    }
                } catch (\Exception $e) {
                    $this->error("  ✗ Ошибка событий: " . $e->getMessage());
                }
            }
        }

        $this->info("Готово.");
    }
}