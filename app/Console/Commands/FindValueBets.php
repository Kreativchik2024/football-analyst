<?php

namespace App\Console\Commands;

use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\ValueBet;
use App\Models\Odd;
use App\Models\Bookmaker;
use App\Models\AiBalance;
use App\Models\UserPrediction;
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
    protected $description = 'Find value bets using ensemble predictions across multiple markets and generate explanations via OpenAI';

    protected ApiFootballService $api;
    protected MarketPredictionAgent $marketAgent;
    protected MlPredictionAgent $mlAgent;
    protected SarimaxPredictionAgent $sarimaxAgent;
    protected NewsPredictionAgent $newsAgent;
    protected EnsembleOrchestrator $orchestrator;
    protected OpenAiService $openai;

    // Поддерживаемые рынки
    protected array $markets = ['1x2', 'over_under_2.5', 'btts'];

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

        // Регистрируем агентов в оркестраторе
        $this->orchestrator->registerAgent('api_football', fn($f) => $this->fetchPrediction($f));
        $this->orchestrator->registerAgent('market',       fn($f) => $this->marketAgent->predict($f));
        $this->orchestrator->registerAgent('ml_model',     fn($f) => $this->mlAgent->predict($f));
        $this->orchestrator->registerAgent('sarimax',      fn($f) => $this->sarimaxAgent->predict($f));
        $this->orchestrator->registerAgent('openai_news',  fn($f) => $this->newsAgent->predict($f));

        $valueBetsFound = 0;

        foreach ($fixtures as $fixture) {
            $this->line("Processing: {$fixture->homeTeam->name} vs {$fixture->awayTeam->name}");

            // 1. Консенсусный прогноз
            $ensemble = $this->orchestrator->predict($fixture);
            if (!$ensemble) {
                $this->warn("  No ensemble prediction available, skipping.");
                continue;
            }
            $this->info("  ✓ Ensemble prediction created");

            // 2. Обрабатываем каждый рынок
            foreach ($this->markets as $market) {
                $this->processMarket($fixture, $ensemble, $market, $valueBetsFound);
            }
        }

        $this->info("✅ Done. Found {$valueBetsFound} value bets.");
    }

    /**
     * Обрабатывает поиск валуйных ставок для одного рынка.
     */
    protected function processMarket(Fixture $fixture, $ensemble, string $market, int &$valueBetsFound): void
{
    $bestOdds = $this->fetchBestOdds($fixture, $market);
    if (empty($bestOdds)) {
        $this->warn("  No odds for {$market}, skipping.");
        return;
    }

    foreach ($bestOdds as $outcome => $oddData) {
        $odd = $oddData['value'] ?? $oddData;
        $oddId = $oddData['odd_id'] ?? 0;
        if (!$odd || $odd <= 0) continue;

        $prob = $this->getMarketProbability($fixture, $ensemble, $market, $outcome);
        if (!$prob || $prob <= 0) continue;

        $impliedProb = 1 / $odd;
        $ev = ($prob * $odd) - 1;
        $edge = ($prob - $impliedProb) * 100;

        if ($ev > 0) {
            // Генерация объяснения через DeepSeek
            $explanation = null;
            if ($market === '1x2') {
                try {
                    $explanation = $this->deepseek->ask(   // ← замена OpenAiService на DeepSeekService
                        "Ты футбольный аналитик. Объясни на русском языке, почему ставка на исход '{$outcome}' выгодна.",
                        "Матч: {$fixture->homeTeam->name} против {$fixture->awayTeam->name}. " .
                        "Вероятность по консенсус-прогнозу: " . round($prob * 100, 1) . "%. " .
                        "Коэффициент букмекера: {$odd}. " .
                        "Ожидаемая ценность (EV): " . round($ev, 3)
                    );
                } catch (\Exception $e) {
                    $this->warn("  ⚠️ Не удалось получить объяснение от DeepSeek: " . $e->getMessage());
                }
            }

            $this->saveValueBet($fixture, $ensemble, $oddId, $outcome, $ev, $edge, $explanation, $market);

            // AI ставит 5% от баланса
            $aiBalance = AiBalance::getBalance();
            $aiStake = round($aiBalance * 0.05, 2);
            if ($aiStake > 0) {
                AiBalance::updateBalance(-$aiStake);
                UserPrediction::create([
                    'user_id'    => null,
                    'fixture_id' => $fixture->id,
                    'market'     => $market,
                    'outcome'    => $outcome,
                    'stake'      => $aiStake,
                    'odds'       => $odd,
                    'status'     => 'pending',
                ]);
                $this->info("  🤖 AI разместил ставку {$aiStake} на {$outcome} (market: {$market})");
            }

            $valueBetsFound++;
        }
    }
}

    /**
     * Получает вероятность исхода для конкретного рынка.
     */
    protected function getMarketProbability(Fixture $fixture, $ensemble, string $market, string $outcome): ?float
    {
        return match ($market) {
            '1x2' => $ensemble?->{$outcome . '_probability'},
            'over_under_2.5' => $this->getOverUnderProbability($fixture, $outcome),
            'btts' => $this->getBttsProbability($fixture, $outcome),
            default => null,
        };
    }

    protected function getOverUnderProbability(Fixture $fixture, string $outcome): ?float
    {
        $response = $this->api->getPredictions($fixture->external_id);
        if (!$response->successful()) return null;

        $data = $response->json('response')[0]['predictions']['over_under'] ?? null;
        if (!$data) return null;

        $prob = $data[$outcome === 'over' ? 'over' : 'under'] ?? null;
        return is_numeric($prob) ? (float)$prob / 100 : null;
    }

    protected function getBttsProbability(Fixture $fixture, string $outcome): ?float
    {
        $response = $this->api->getPredictions($fixture->external_id);
        if (!$response->successful()) return null;

        $data = $response->json('response')[0]['predictions']['btts'] ?? null;
        if (!$data) return null;

        $prob = $data[$outcome === 'yes' ? 'yes' : 'no'] ?? null;
        return is_numeric($prob) ? (float)$prob / 100 : null;
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

    protected function fetchBestOdds(Fixture $fixture, string $market): array
    {
        $odds = Odd::where('fixture_id', $fixture->id)
            ->where('market', $market)
            ->get()
            ->groupBy('outcome')
            ->map(fn($group) => $group->max('value'));

        if ($odds->isEmpty()) {
            $response = $this->api->getOdds($fixture->external_id);
            if (!$response->successful()) return [];

            $data = $response->json('response')[0] ?? [];
            $bookmakersData = $data['bookmakers'] ?? [];
            $this->saveOddsFromApi($fixture, $bookmakersData);
            $odds = Odd::where('fixture_id', $fixture->id)
                ->where('market', $market)
                ->get()
                ->groupBy('outcome')
                ->map(fn($group) => $group->max('value'));
        }

        return $odds->toArray();
    }

    protected function saveOddsFromApi(Fixture $fixture, array $bookmakersData): void
    {
        foreach ($bookmakersData as $bookmakerData) {
            $bookmaker = Bookmaker::updateOrCreate(
                ['external_id' => $bookmakerData['id']],
                ['name' => $bookmakerData['name']]
            );

            foreach ($bookmakerData['bets'] ?? [] as $bet) {
                foreach ($bet['values'] ?? [] as $outcomeData) {
                    $outcome = $this->mapOutcome($outcomeData['value']);
                    if (!$outcome) continue;

                    Odd::updateOrCreate(
                        [
                            'fixture_id'   => $fixture->id,
                            'bookmaker_id' => $bookmaker->id,
                            'market'       => $bet['name'] ?? '1x2',
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
            'Over' => 'over',
            'Under' => 'under',
            'Yes' => 'yes',
            'No' => 'no',
            default => null,
        };
    }

    /**
     * Сохраняет валуйную ставку с объяснением и типом рынка.
     */
    protected function saveValueBet(
        Fixture $fixture,
        $prediction,
        int $oddId,
        string $outcome,
        float $ev,
        float $edge,
        ?string $explanation = null,
        string $market = '1x2'
    ): void {
        ValueBet::updateOrCreate(
            [
                'fixture_id' => $fixture->id,
                'market'     => $market,
                'outcome'    => $outcome,
            ],
            [
                'prediction_id'  => $prediction->id ?? null,
                'odd_id'         => $oddId,
                'expected_value' => $ev,
                'edge_percent'   => $edge,
                'explanation'    => $explanation,
                'status'         => 'pending',
                'type'           => 'prematch',
            ]
        );
        $this->info("  💰 Value bet found: {$outcome} (market: {$market}) @ EV = " . round($ev, 3));
    }
}