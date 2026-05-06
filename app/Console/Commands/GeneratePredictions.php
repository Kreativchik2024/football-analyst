<?php

namespace App\Console\Commands;

use App\Models\Fixture;
use App\Models\Prediction;
use App\Services\ApiFootballService;
use App\Services\Agents\MarketPredictionAgent;
use App\Services\Agents\MlPredictionAgent;
use App\Services\Agents\SarimaxPredictionAgent;
use App\Services\Agents\NewsPredictionAgent;
use App\Services\Agents\EnsembleOrchestrator;
use Illuminate\Console\Command;
use Carbon\Carbon;

class GeneratePredictions extends Command
{
    protected $signature = 'predictions:generate';
    protected $description = 'Generate predictions from all agents and ensemble for upcoming fixtures';

    protected ApiFootballService $api;
    protected MarketPredictionAgent $marketAgent;
    protected MlPredictionAgent $mlAgent;
    protected SarimaxPredictionAgent $sarimaxAgent;
    protected NewsPredictionAgent $newsAgent;
    protected EnsembleOrchestrator $orchestrator;

    public function __construct(
        ApiFootballService $api,
        MarketPredictionAgent $marketAgent,
        MlPredictionAgent $mlAgent,
        SarimaxPredictionAgent $sarimaxAgent,
        NewsPredictionAgent $newsAgent,
        EnsembleOrchestrator $orchestrator
    ) {
        parent::__construct();
        $this->api = $api;
        $this->marketAgent = $marketAgent;
        $this->mlAgent = $mlAgent;
        $this->sarimaxAgent = $sarimaxAgent;
        $this->newsAgent = $newsAgent;
        $this->orchestrator = $orchestrator;
    }

    public function handle()
    {
        $fixtures = Fixture::whereIn('status', ['NS', 'TBD', 'PST'])
            ->where('starting_at', '>=', Carbon::now())
            ->where('starting_at', '<=', Carbon::now()->addDays(7))
            ->with(['homeTeam', 'awayTeam'])
            ->get();

        $this->info('Generating predictions for ' . $fixtures->count() . ' upcoming fixtures...');

        // Регистрируем агентов в оркестраторе
        $this->orchestrator->registerAgent('api_football', fn($f) => $this->getApiFootballPrediction($f));
        $this->orchestrator->registerAgent('market',       fn($f) => $this->marketAgent->predict($f));
        $this->orchestrator->registerAgent('ml_model',     fn($f) => $this->mlAgent->predict($f));
        $this->orchestrator->registerAgent('sarimax',      fn($f) => $this->sarimaxAgent->predict($f));
        $this->orchestrator->registerAgent('openai_news',  fn($f) => $this->newsAgent->predict($f));

        foreach ($fixtures as $fixture) {
            $this->line("Fixture: {$fixture->homeTeam->name} vs {$fixture->awayTeam->name}");

            // Генерируем прогнозы от каждого агента
            foreach (['api_football', 'market', 'ml_model', 'sarimax', 'openai_news'] as $type) {
                $callback = $this->getAgentCallback($type);
                if ($callback) {
                    try {
                        $prediction = $callback($fixture);
                        if ($prediction) {
                            $this->info("  ✓ {$type}");
                        } else {
                            $this->warn("  ✗ {$type} (no data)");
                        }
                    } catch (\Exception $e) {
                        $this->error("  ✗ {$type} error: " . $e->getMessage());
                    }
                }
            }

            // Формируем консенсусный прогноз
            $ensemble = $this->orchestrator->predict($fixture);
            if ($ensemble) {
                $this->info("  ✓ Ensemble prediction created");
            }
        }

        $this->info('Done.');
    }

    protected function getAgentCallback(string $type): ?callable
    {
        return match ($type) {
            'api_football' => fn($f) => $this->getApiFootballPrediction($f),
            'market'       => fn($f) => $this->marketAgent->predict($f),
            'ml_model'     => fn($f) => $this->mlAgent->predict($f),
            'sarimax'      => fn($f) => $this->sarimaxAgent->predict($f),
            'openai_news'  => fn($f) => $this->newsAgent->predict($f),
            default => null
        };
    }

    protected function getApiFootballPrediction(Fixture $fixture): ?Prediction
    {
        $prediction = Prediction::where('fixture_id', $fixture->id)
            ->where('agent_type', 'api_football')
            ->first();
        if ($prediction) return $prediction;

        $response = $this->api->getPredictions($fixture->external_id);
        if (!$response->successful()) return null;
        $data = $response->json('response')[0] ?? null;
        if (!$data) return null;

        $homeProb = isset($data['predictions']['percent']['home']) 
            ? (int) rtrim($data['predictions']['percent']['home'], '%') / 100 : 0;
        $drawProb = isset($data['predictions']['percent']['draw']) 
            ? (int) rtrim($data['predictions']['percent']['draw'], '%') / 100 : 0;
        $awayProb = isset($data['predictions']['percent']['away']) 
            ? (int) rtrim($data['predictions']['percent']['away'], '%') / 100 : 0;

        return Prediction::updateOrCreate(
            [
                'fixture_id'  => $fixture->id,
                'agent_type'  => 'api_football',
                'model_version' => 'api_football',
            ],
            [
                'home_probability'  => $homeProb,
                'draw_probability'  => $drawProb,
                'away_probability'  => $awayProb,
                'features_used'     => null,
            ]
        );
    }
}