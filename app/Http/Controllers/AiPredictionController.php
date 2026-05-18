<?php

namespace App\Http\Controllers;

use App\Models\Fixture;
use App\Models\Prediction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class AiPredictionController extends Controller
{
    // Список всех агентов, включая ваши ML‑модели
    protected $agents = [
        'api_football',
        'market',
        'ml_model',
        'sarimax',
        'openai_news',
        'xgboost',        // ← ваша XGBoost модель
        'orchestrator',   // ← главный ассистент (ансамбль)
    ];

    public function index(Request $request)
    {
        $upcomingFixtures = Fixture::with(['homeTeam', 'awayTeam', 'predictions', 'ensemblePrediction'])
            ->where('starting_at', '>=', now())
            ->whereIn('status', ['NS', 'TBD', 'PST'])
            ->where('starting_at', '<=', now()->addDays(7))
            ->orderBy('starting_at')
            ->get();

        // Кэшируем статистику агентов на 1 час (обновляется раз в час)
        $agentStats = Cache::remember('agent_stats', 3600, function () {
            return $this->getAgentStatistics();
        });

        return view('ai.predictions', compact('upcomingFixtures', 'agentStats'));
    }

    /**
     * Рассчитывает точность каждого агента на завершённых матчах.
     */
    protected function getAgentStatistics(): array
    {
        $completedPredictions = Prediction::whereHas('fixture', function ($q) {
            $q->whereIn('status', ['FT', 'AET', 'PEN']);
        })->with('fixture')->get();

        $stats = [];

        foreach ($this->agents as $agent) {
            $agentPredictions = $completedPredictions->where('agent_type', $agent);
            $total = $agentPredictions->count();

            if ($total === 0) {
                $stats[$agent] = [
                    'total'    => 0,
                    'correct'  => 0,
                    'accuracy' => 0,
                ];
                continue;
            }

            $correct = 0;

            foreach ($agentPredictions as $pred) {
                $fixture = $pred->fixture;
                // Пропускаем матчи без результата (на всякий случай)
                if (!$fixture || $fixture->home_score === null) {
                    continue;
                }

                // Реальный исход
                $realOutcome = $fixture->home_score > $fixture->away_score ? 'home'
                            : ($fixture->home_score < $fixture->away_score ? 'away' : 'draw');

                // Предсказанный исход (берём максимальную вероятность)
                $probs = [
                    'home' => (float) $pred->home_probability,
                    'draw' => (float) $pred->draw_probability,
                    'away' => (float) $pred->away_probability,
                ];
                $predictedOutcome = array_keys($probs, max($probs))[0];

                if ($predictedOutcome === $realOutcome) {
                    $correct++;
                }
            }

            $stats[$agent] = [
                'total'    => $total,
                'correct'  => $correct,
                'accuracy' => round(($correct / $total) * 100, 1),
            ];
        }

        return $stats;
    }

    /**
     * Обновить прогнозы для всех предстоящих матчей через ML‑сервер.
     * Вызывать можно по cron или через artisan-команду.
     */
    public function refreshPredictions()
    {
        // Берём предстоящие матчи, для которых ещё нет прогноза от xgboost/orchestrator
        $fixtures = Fixture::with(['homeTeam', 'awayTeam'])
            ->upcoming()
            ->nextDays(7)
            ->get();

        // Собираем данные для отправки в ML‑сервер
        $matchesForML = [];
        foreach ($fixtures as $fixture) {
            $matchesForML[] = [
                'match_id'   => $fixture->id,
                'home_team'  => $fixture->homeTeam->name,
                'away_team'  => $fixture->awayTeam->name,
                'match_date' => $fixture->starting_at->toDateString(),
                'news'       => [], // позже можно подгрузить новости из таблицы `news`
            ];
        }

        if (empty($matchesForML)) {
            return response()->json(['message' => 'No upcoming matches to predict']);
        }

        // Отправляем batch-запрос в ML‑сервер
        try {
            $response = Http::timeout(60)->post('http://127.0.0.1:8002/predict-batch', [
                'matches' => $matchesForML,
            ]);

            if (!$response->successful()) {
                return response()->json(['error' => 'ML server error'], 500);
            }

            $predictions = $response->json('predictions', []);

            // Сохраняем каждый прогноз в БД
            $saved = 0;
            foreach ($predictions as $pred) {
                Prediction::updateOrCreate(
                    [
                        'fixture_id' => $pred['match_id'],
                        'agent_type' => 'orchestrator', // или 'xgboost'
                    ],
                    [
                        'home_probability' => $pred['probabilities']['home_win'],
                        'draw_probability'  => $pred['probabilities']['draw'],
                        'away_probability'  => $pred['probabilities']['away_win'],
                        'model_version'     => 'ensemble_v1',
                    ]
                );
                $saved++;
            }

            // Очищаем кэш статистики, чтобы при следующем показе обновилась точность
            Cache::forget('agent_stats');

            return response()->json(['saved' => $saved]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Если нужно показать статистику в JSON (для AJAX).
     */
    public function stats()
    {
        return response()->json($this->getAgentStatistics());
    }
}