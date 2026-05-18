<?php
// app/Console/Commands/DataAudit.php

namespace App\Console\Commands;

use App\Models\Fixture;
use App\Models\Team;
use App\Models\League;
use App\Models\Prediction;
use App\Models\Odd;
use App\Models\MatchStatistic;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class DataAudit extends Command
{
    protected $signature = 'data:audit 
                            {--days=30 : За сколько дней проверять}
                            {--show-missing : Показать пропущенные матчи}
                            {--check-unloaded : Проверить незагруженные матчи через API}
                            {--league= : ID лиги для проверки (только с --check-unloaded)}';

    protected $description = 'Аудит загруженных данных в БД';

    protected $apiKey;
    protected $baseUrl = 'https://v3.football.api-sports.io';

    public function __construct()
    {
        parent::__construct();
        $this->apiKey = env('API_FOOTBALL_KEY');
    }

    public function handle()
    {
        $days = (int) $this->option('days');
        
        $this->info("📊 АУДИТ БАЗЫ ДАННЫХ");
        $this->line("Период: последние {$days} дней");
        $this->line("Дата: " . Carbon::now()->format('Y-m-d H:i:s'));
        $this->newLine();

        // 1. Общая статистика
        $this->showGeneralStats();
        
        // 2. Матчи по дням
        $this->showMatchesByDay($days);
        
        // 3. Завершенные матчи без xG
        $this->showMissingXG();
        
        // 4. Предстоящие матчи без прогнозов
        $this->showMissingPredictions();
        
        // 5. Проверка незагруженных матчей (НОВАЯ!)
        if ($this->option('check-unloaded')) {
            $this->showUnloadedFixtures();
        }
        
        // 6. Пропущенные матчи
        if ($this->option('show-missing')) {
            $this->showMissingFixtures();
        }
    }

    // ... остальные методы (showGeneralStats, showMatchesByDay, showMissingXG, showMissingPredictions)

    /**
     * НОВЫЙ МЕТОД: Проверка незагруженных матчей через API
     */
    private function showUnloadedFixtures()
    {
        $this->info("🔍 ПРОВЕРКА НЕЗАГРУЖЕННЫХ МАТЧЕЙ");
        
        $leagueId = $this->option('league');
        $currentYear = Carbon::now()->year;
        $season = Carbon::now()->month >= 8 ? $currentYear : $currentYear - 1;
        
        // Получаем список лиг для проверки
        if ($leagueId) {
            $leagueIds = [(int) $leagueId];
        } else {
            // Основные лиги
            $leagueIds = [39, 40, 140, 135, 78, 61, 94, 71, 253, 1, 2, 3];
        }
        
        $allUnloaded = [];
        
        foreach ($leagueIds as $lid) {
            $this->line("Проверка лиги ID: {$lid}...");
            
            try {
                // Получаем матчи из API за последние 30 дней
                $from = Carbon::now()->subDays(30)->toDateString();
                $to = Carbon::now()->toDateString();
                
                $response = Http::withHeaders([
                    'x-apisports-key' => $this->apiKey,
                ])
                    ->timeout(30)
                    ->get("{$this->baseUrl}/fixtures", [
                        'league' => $lid,
                        'season' => $season,
                        'from'   => $from,
                        'to'     => $to,
                    ]);
                
                if (!$response->successful()) {
                    $this->warn("  Ошибка API для лиги {$lid}: " . $response->status());
                    continue;
                }
                
                $apiFixtures = $response->json()['response'] ?? [];
                
                // Получаем ID матчей из БД для этой лиги
                $dbFixtureIds = Fixture::where('league_id', function($q) use ($lid) {
                    $q->select('id')->from('leagues')->where('external_id', $lid);
                })->pluck('external_id')->toArray();
                
                // Находим незагруженные матчи
                $unloaded = [];
                foreach ($apiFixtures as $fixture) {
                    $apiId = $fixture['fixture']['id'];
                    if (!in_array($apiId, $dbFixtureIds)) {
                        $unloaded[] = [
                            'id' => $apiId,
                            'date' => $fixture['fixture']['date'],
                            'home' => $fixture['teams']['home']['name'],
                            'away' => $fixture['teams']['away']['name'],
                            'status' => $fixture['fixture']['status']['short'],
                        ];
                    }
                }
                
                if (!empty($unloaded)) {
                    $this->warn("  Найдено незагруженных матчей: " . count($unloaded));
                    foreach (array_slice($unloaded, 0, 10) as $u) {
                        $this->line("    - ID:{$u['id']} {$u['date']} {$u['home']} vs {$u['away']} ({$u['status']})");
                    }
                    $allUnloaded = array_merge($allUnloaded, $unloaded);
                } else {
                    $this->line("  ✅ Все матчи загружены");
                }
                
                sleep(1); // Пауза между запросами
                
            } catch (\Exception $e) {
                $this->error("  Ошибка: " . $e->getMessage());
            }
        }
        
        if (!empty($allUnloaded)) {
            $this->newLine();
            $this->warn("📋 ИТОГО НЕЗАГРУЖЕННЫХ МАТЧЕЙ: " . count($allUnloaded));
            $this->line("\n💡 Чтобы загрузить пропущенные матчи, выполните:");
            
            // Группируем по датам для рекомендации
            $dates = array_unique(array_map(function($m) {
                return substr($m['date'], 0, 10);
            }, $allUnloaded));
            
            foreach ($dates as $date) {
                $this->line("   php artisan fetch:season {$season} --from={$date} --to={$date}");
            }
        } else {
            $this->info("✅ Все матчи загружены!");
        }
        
        $this->newLine();
    }

    private function showGeneralStats()
    {
        $this->info("📈 ОБЩАЯ СТАТИСТИКА");
        
        $leagues = League::count();
        $teams = Team::count();
        $fixtures = Fixture::count();
        $finished = Fixture::finished()->count();
        $upcoming = Fixture::upcoming()->count();
        $withXG = Fixture::whereNotNull('home_xg')->count();
        $predictions = Prediction::count();
        $odds = Odd::count();
        $statistics = MatchStatistic::count();
        
        // Получаем диапазон дат
        $oldest = Fixture::min('starting_at');
        $newest = Fixture::max('starting_at');
        
        $this->table(
            ['Показатель', 'Количество'],
            [
                ['Лиги', $leagues],
                ['Команды', $teams],
                ['Всего матчей', $fixtures],
                ['Завершенные матчи', $finished],
                ['Предстоящие матчи', $upcoming],
                ['Матчи с xG', $withXG . ' (' . round($withXG / max($finished, 1) * 100) . '%)'],
                ['Прогнозы API', $predictions],
                ['Коэффициенты', $odds],
                ['Записей статистики', $statistics],
                ['Диапазон дат', substr($oldest, 0, 10) . ' — ' . substr($newest, 0, 10)],
            ]
        );
        $this->newLine();
    }

    private function showMatchesByDay($days)
    {
        $this->info("📅 МАТЧИ ПО ДНЯМ (последние {$days} дней)");
        
        $matches = Fixture::where('starting_at', '>=', Carbon::now()->subDays($days))
            ->orderBy('starting_at', 'desc')
            ->get()
            ->groupBy(function($fixture) {
                return $fixture->starting_at->format('Y-m-d');
            });
        
        if ($matches->isEmpty()) {
            $this->line("Нет матчей за этот период");
            $this->newLine();
            return;
        }
        
        $rows = [];
        foreach ($matches as $date => $dayMatches) {
            $total = $dayMatches->count();
            $finished = $dayMatches->whereIn('status', ['FT', 'AET', 'PEN'])->count();
            $withXG = $dayMatches->whereNotNull('home_xg')->count();
            $withPredictions = $dayMatches->filter(function($m) {
                return $m->predictions->where('agent_type', 'api_football')->count() > 0;
            })->count();
            
            $statusIcon = ($finished == $total && $total > 0) ? '✅' : '⚠️';
            $rows[] = [$date, $total, $finished, $withXG, $withPredictions, $statusIcon];
        }
        
        $this->table(
            ['Дата', 'Матчей', 'Завершено', 'С xG', 'С прогнозами', 'Статус'],
            $rows
        );
        $this->newLine();
    }

    private function showMissingXG()
    {
        $this->info("⚠️ ЗАВЕРШЕННЫЕ МАТЧИ БЕЗ XG");
        
        $missing = Fixture::finished()
            ->whereNull('home_xg')
            ->where('starting_at', '>=', Carbon::now()->subDays(30))
            ->with(['homeTeam', 'awayTeam', 'league'])
            ->limit(20)
            ->get();
        
        if ($missing->isEmpty()) {
            $this->line("✅ Все завершенные матчи имеют xG!");
        } else {
            $rows = [];
            foreach ($missing as $fixture) {
                $rows[] = [
                    $fixture->id,
                    $fixture->starting_at->format('Y-m-d'),
                    $fixture->league->name ?? '?',
                    $fixture->homeTeam->name ?? '?',
                    $fixture->awayTeam->name ?? '?',
                ];
            }
            $this->table(['ID', 'Дата', 'Лига', 'Хозяева', 'Гости'], $rows);
            $this->line("\n💡 Для загрузки xG выполните:");
            $this->line("   php artisan fetch:season " . date('Y') . " --from=2026-04-01 --to=2026-05-18");
        }
        $this->newLine();
    }

    private function showMissingPredictions()
    {
        $this->info("🎯 ПРЕДСТОЯЩИЕ МАТЧИ БЕЗ ПРОГНОЗОВ");
        
        $missing = Fixture::upcoming()
            ->whereDoesntHave('predictions', function($q) {
                $q->where('agent_type', 'api_football');
            })
            ->where('starting_at', '<=', Carbon::now()->addDays(7))
            ->with(['homeTeam', 'awayTeam'])
            ->limit(20)
            ->get();
        
        if ($missing->isEmpty()) {
            $this->line("✅ Все ближайшие матчи имеют прогнозы!");
        } else {
            $rows = [];
            foreach ($missing as $fixture) {
                $rows[] = [
                    $fixture->id,
                    $fixture->starting_at->format('Y-m-d H:i'),
                    $fixture->homeTeam->name ?? '?',
                    $fixture->awayTeam->name ?? '?',
                ];
            }
            $this->table(['ID', 'Дата', 'Хозяева', 'Гости'], $rows);
            $this->line("\n💡 Для загрузки прогнозов выполните:");
            $this->line("   php artisan fetch:predictions --days=14 --update-existing");
        }
        $this->newLine();
    }

    private function showMissingFixtures()
    {
        $this->info("🔍 ПРОВЕРКА ПРОПУСКОВ В ID");
        
        $minId = Fixture::min('id');
        $maxId = Fixture::max('id');
        
        if (!$minId || !$maxId) {
            $this->line("Нет данных для анализа");
            return;
        }
        
        $total = $maxId - $minId + 1;
        $existing = Fixture::count();
        $gap = $total - $existing;
        
        if ($gap > 0 && $gap < 1000) {
            $this->warn("Возможные пропуски: ~{$gap} записей");
            
            $existingIds = Fixture::pluck('id')->toArray();
            $missingIds = [];
            
            for ($i = $minId; $i <= $maxId; $i++) {
                if (!in_array($i, $existingIds)) {
                    $missingIds[] = $i;
                    if (count($missingIds) >= 20) break;
                }
            }
            
            if (!empty($missingIds)) {
                $this->table(['Пропущенные ID'], array_map(function($id) {
                    return [$id];
                }, $missingIds));
            }
        } else {
            $this->line("✅ Не обнаружено явных пропусков в ID");
        }
        $this->newLine();
    }
}