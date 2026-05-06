<?php

namespace App\Console\Commands;

use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\ValueBet;
use App\Models\Odd;
use App\Models\Bookmaker;
use App\Services\ApiFootballService;
use App\Services\Agents\MarketPredictionAgent;
use App\Services\Agents\MlPredictionAgent;
use App\Services\Agents\SarimaxPredictionAgent;
use App\Services\Agents\NewsPredictionAgent;
use App\Services\Agents\EnsembleOrchestrator;
use App\Services\OpenAiService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class FindValueBets extends Command
{
    protected $signature = 'bets:find-value';
    protected $description = 'Find value bets using ensemble predictions and generate explanations via OpenAI';

    protected ApiFootballService $api;
    protected MarketPredictionAgent $marketAgent;
    protected MlPredictionAgent $mlAgent;
    protected SarimaxPredictionAgent $sarimaxAgent;
    protected NewsPredictionAgent $newsAgent;
    protected EnsembleOrchestrator $orchestrator;
    protected OpenAiService $openai;

    public function __construct(
        ApiFootballService $api,
        MarketPredictionAgent $marketAgent,
        MlPredictionAgent $mlAgent,
        SarimaxPredictionAgent $sarimaxAgent,
        NewsPredictionAgent $newsAgent,
        EnsembleOrchestrator $orchestrator,
        OpenAiService $openai
    ) {
        parent::__construct();
        $this->api = $api;
        $this->marketAgent = $marketAgent;
        $this->mlAgent = $mlAgent;
        $this->sarimaxAgent = $sarimaxAgent;
        $this->newsAgent = $newsAgent;
        $this->orchestrator = $orchestrator;
        $this->openai = $openai;
    }

    public function handle()
    {
        $this->info('Searching for value bets...');

        $fixtures = Fixture::whereIn('status', ['NS', 'TBD', 'PST'])
            ->where('starting_at', '>=', Carbon::now())
            ->where('starting_at', '<=', Carbon::now()->addDays(7))
            ->with(['homeTeam', 'awayTeam'])
            ->get();

        $this->info('Found ' . $fixtures->count() . ' upcoming fixtures.');

        // Регистрируем всех агентов в оркестраторе (один раз перед циклом)
        $this->orchestrator->registerAgent('api_football', fn($f) => $this->fetchPrediction($f));
        $this->orchestrator->registerAgent('market',       fn($f) => $this->marketAgent->predict($f));
        $this->orchestrator->registerAgent('ml_model',     fn($f) => $this->mlAgent->predict($f));
        $this->orchestrator->registerAgent('sarimax',      fn($f) => $this->sarimaxAgent->predict($f));
        $this->orchestrator->registerAgent('openai_news',  fn($f) => $this->newsAgent->predict($f));

        $valueBetsFound = 0;

        foreach ($fixtures as $fixture) {
            $this->line("Processing: {$fixture->homeTeam->name} vs {$fixture->awayTeam->name}");

            // 1. Получаем консенсусный прогноз от оркестратора
            $ensemble = $this->orchestrator->predict($fixture);

            if (!$ensemble) {
                $this->warn("  No ensemble prediction available, skipping.");
                continue;
            }

            $this->info("  ✓ Ensemble prediction created");

            // 2. Лучшие коэффициенты
            $bestOdds = $this->fetchBestOdds($fixture);
            if (empty($bestOdds)) {
                $this->warn("  No odds available, skipping.");
                continue;
            }

            // 3. Поиск валуйных ставок на основе консенсус-прогноза
            foreach (['home', 'draw', 'away'] as $outcome) {
                $prob = $ensemble->{$outcome . '_probability'};
                $odd = $bestOdds[$outcome]['value'] ?? null;
                if (!$odd || $prob <= 0) continue;

                $impliedProb = 1 / $odd;
                $ev = ($prob * $odd) - 1;
                $edge = ($prob - $impliedProb) * 100;

                if ($ev > 0) {
                    // === Генерация объяснения через OpenAI ===
                    $explanation = null;
                    try {
                        $explanation = $this->openai->ask(
                            "Ты футбольный аналитик. Объясни на русском языке, почему ставка на исход '{$outcome}' выгодна.",
                            "Матч: {$fixture->homeTeam->name} против {$fixture->awayTeam->name}. " .
                            "Вероятность по консенсус-прогнозу: " . round($prob * 100, 1) . "%. " .
                            "Коэффициент букмекера: {$odd}. " .
                            "Ожидаемая ценность (EV): " . round($ev, 3)
                        );
                    } catch (\Exception $e) {
                        $this->warn("  ⚠️ Не удалось получить объяснение от OpenAI: " . $e->getMessage());
                    }

                    $this->saveValueBet(
                        $fixture,
                        $ensemble,   // передаём EnsemblePrediction (имеет те же поля вероятностей)
                        $bestOdds[$outcome]['odd_id'],
                        $outcome,
                        $ev,
                        $edge,
                        $explanation
                    );
                    $valueBetsFound++;
                }
            }
        }

        $this->info("✅ Done. Found {$valueBetsFound} value bets.");
    }

    protected function fetchPrediction(Fixture $fixture): ?Prediction
    {
        $prediction = Prediction::where('fixture_id', $fixture->id)
            ->where('model_version', 'api_football')
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

        return Prediction::create([
            'fixture_id'        => $fixture->id,
            'agent_type'        => 'api_football',
            'home_probability'  => $homeProb,
            'draw_probability'  => $drawProb,
            'away_probability'  => $awayProb,
            'model_version'     => 'api_football',
            'features_used'     => null,
        ]);
    }

    protected function fetchBestOdds(Fixture $fixture): array
    {
        $odds = Odd::where('fixture_id', $fixture->id)
            ->where('market', '1x2')
            ->get();

        if ($odds->isEmpty()) {
            $response = $this->api->getOdds($fixture->external_id);
            if (!$response->successful()) return [];

            $data = $response->json('response')[0] ?? [];
            $bookmakersData = $data['bookmakers'] ?? [];
            $this->saveOddsFromApi($fixture, $bookmakersData);
            $odds = Odd::where('fixture_id', $fixture->id)->where('market', '1x2')->get();
        }

        $best = ['home' => null, 'draw' => null, 'away' => null];
        foreach ($odds as $odd) {
            $outcome = $odd->outcome;
            if (!isset($best[$outcome]) || $odd->value > $best[$outcome]['value']) {
                $best[$outcome] = ['value' => $odd->value, 'odd_id' => $odd->id];
            }
        }
        return $best;
    }

    protected function saveOddsFromApi(Fixture $fixture, array $bookmakersData): void
    {
        foreach ($bookmakersData as $bookmakerData) {
            $bookmaker = Bookmaker::updateOrCreate(
                ['external_id' => $bookmakerData['id']],
                ['name' => $bookmakerData['name']]
            );

            foreach ($bookmakerData['bets'] ?? [] as $bet) {
                if ($bet['name'] !== 'Match Winner') continue;
                foreach ($bet['values'] ?? [] as $outcomeData) {
                    $outcome = $this->mapOutcome($outcomeData['value']);
                    if (!$outcome) continue;

                    Odd::updateOrCreate(
                        [
                            'fixture_id'   => $fixture->id,
                            'bookmaker_id' => $bookmaker->id,
                            'market'       => '1x2',
                            'outcome'      => $outcome,
                        ],
                        [
                            'value'      => $outcomeData['odd'],
                            'fetched_at' => now(),
                        ]
                    );
                }
            }
        }
    }

    protected function mapOutcome(string $apiValue): ?string
    {
        return match ($apiValue) {
            'Home' => 'home',
            'Draw' => 'draw',
            'Away' => 'away',
            default => null,
        };
    }

    /**
     * Сохраняет валуйную ставку с объяснением.
     * Принимает объект с полями вероятностей (Prediction или EnsemblePrediction).
     */
    protected function saveValueBet(
        Fixture $fixture,
        $prediction,
        int $oddId,
        string $betType,
        float $ev,
        float $edge,
        ?string $explanation = null
    ): void {
        ValueBet::updateOrCreate(
            [
                'fixture_id'    => $fixture->id,
                'odd_id'        => $oddId,
                'bet_type'      => $betType,
            ],
            [
                'prediction_id' => $prediction->id ?? null,
                'expected_value'=> $ev,
                'edge_percent'  => $edge,
                'explanation'   => $explanation,
                'status'        => 'pending',
            ]
        );
        $this->info("  💰 Value bet found: {$betType} @ EV = " . round($ev, 3));
    }
}