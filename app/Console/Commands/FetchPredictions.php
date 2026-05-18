<?php
// app/Console/Commands/FetchPredictions.php

namespace App\Console\Commands;

use App\Models\Fixture;
use App\Models\Prediction;
use App\Services\ApiFootballService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class FetchPredictions extends Command
{
    protected $signature = 'fetch:predictions 
                            {--days=7 : На сколько дней вперед}
                            {--update-existing : Обновить существующие прогнозы}';

    protected $description = 'Загрузить прогнозы от API‑Football для предстоящих матчей';

    protected ApiFootballService $api;

    public function __construct(ApiFootballService $api)
    {
        parent::__construct();
        $this->api = $api;
    }

    public function handle()
    {
        $days = (int) $this->option('days');
        $updateExisting = $this->option('update-existing');

        // Получаем предстоящие матчи
        $fixtures = Fixture::where('starting_at', '>', now())
            ->where('starting_at', '<', now()->addDays($days))
            ->when(!$updateExisting, function($query) {
                $query->whereDoesntHave('predictions', function($sub) {
                    $sub->where('agent_type', 'api_football');
                });
            })
            ->get();

        $this->info("Найдено матчей для прогнозов: " . $fixtures->count());

        foreach ($fixtures as $fixture) {
            $this->line("Матч: {$fixture->homeTeam->name} vs {$fixture->awayTeam->name}");

            try {
                // Передаем ID матча
                $response = $this->api->getPredictions($fixture->external_id);

                if (!$response->successful()) {
                    $this->warn("  Ошибка API: {$response->status()}");
                    continue;
                }

                $data = $response->json();
                $predictions = $data['response'] ?? [];

                if (empty($predictions)) {
                    $this->warn("  Нет прогнозов для этого матча");
                    
                    // Сохраняем значения по умолчанию, если прогнозов нет
                    Prediction::updateOrCreate(
                        [
                            'fixture_id' => $fixture->id,
                            'agent_type' => 'api_football',
                        ],
                        [
                            'home_probability' => 0.33,
                            'draw_probability' => 0.34,
                            'away_probability' => 0.33,
                            'model_version' => 'default',
                            'features_used' => json_encode(['note' => 'no predictions from API']),
                        ]
                    );
                    continue;
                }

                $prediction = $predictions[0];
                $comparison = $prediction['comparison'] ?? [];
                
                // Извлекаем вероятности
                $homeProb = $this->extractProbability($comparison, 'home');
                $drawProb = $this->extractProbability($comparison, 'draw');
                $awayProb = $this->extractProbability($comparison, 'away');

                // Сохраняем прогноз - явно указываем agent_type
                $saved = Prediction::updateOrCreate(
                    [
                        'fixture_id' => $fixture->id,
                        'agent_type' => 'api_football',
                    ],
                    [
                        'home_probability' => $homeProb,
                        'draw_probability' => $drawProb,
                        'away_probability' => $awayProb,
                        'model_version' => $prediction['predictions']['advice'] ?? 'v1',
                        'features_used' => json_encode([
                            'form' => $comparison['form'] ?? null,
                            'attack' => $comparison['attack'] ?? null,
                            'defense' => $comparison['defense'] ?? null,
                            'h2h' => $comparison['h2h'] ?? null,
                        ]),
                    ]
                );

                if ($saved) {
                    $this->info("  ✅ Прогноз сохранен: H:{$homeProb} D:{$drawProb} A:{$awayProb}");
                } else {
                    $this->error("  ❌ Не удалось сохранить прогноз");
                }
                
            } catch (\Exception $e) {
                $this->error("  ❌ Ошибка: " . $e->getMessage());
            }
            
            usleep(200000); // 0.2 сек пауза
        }
        
        $this->info("✅ Загрузка прогнозов завершена");
    }

    private function extractProbability(array $comparison, string $side): float
    {
        // Пытаемся извлечь процент из строки типа "51%" или 51.0
        if (isset($comparison[$side])) {
            $value = $comparison[$side];
            if (is_string($value) && preg_match('/(\d+(?:\.\d+)?)/', $value, $matches)) {
                return floatval($matches[1]) / 100;
            }
            if (is_numeric($value)) {
                return floatval($value) / 100;
            }
        }
        
        // Значение по умолчанию
        return 0.33;
    }
}