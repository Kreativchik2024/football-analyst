<?php

namespace App\Services\Agents;

use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\EnsemblePrediction;
use Illuminate\Support\Facades\Log;

class EnsembleOrchestrator
{
    protected array $agents = [];

    public function registerAgent(string $type, callable $callback): void
    {
        $this->agents[$type] = $callback;
    }

    public function predict(Fixture $fixture): ?EnsemblePrediction
    {
        $predictions = [];
        foreach ($this->agents as $type => $callback) {
            try {
                $pred = $callback($fixture);
                if ($pred) {
                    $predictions[$type] = $pred;
                }
            } catch (\Exception $e) {
                Log::warning("Orchestrator: агент {$type} не сработал: " . $e->getMessage());
            }
        }

        if (count($predictions) < 2) {
            return null;
        }

        $weights = $this->getAgentWeights();

        $homeProb = 0;
        $drawProb = 0;
        $awayProb = 0;
        $totalWeight = 0;

        foreach ($predictions as $type => $pred) {
            $weight = $weights[$type] ?? 1.0;
            $homeProb += $pred->home_probability * $weight;
            $drawProb += $pred->draw_probability * $weight;
            $awayProb += $pred->away_probability * $weight;
            $totalWeight += $weight;
        }

        $homeProb /= $totalWeight;
        $drawProb /= $totalWeight;
        $awayProb /= $totalWeight;

        $outcomes = [
            ['outcome' => 'home', 'probability' => $homeProb],
            ['outcome' => 'draw', 'probability' => $drawProb],
            ['outcome' => 'away', 'probability' => $awayProb],
        ];
        usort($outcomes, fn($a, $b) => $b['probability'] <=> $a['probability']);

        return EnsemblePrediction::updateOrCreate(
            ['fixture_id' => $fixture->id],
            [
                'home_probability' => $homeProb,
                'draw_probability' => $drawProb,
                'away_probability' => $awayProb,
                'top3_outcomes'    => json_encode($outcomes),
            ]
        );
    }

    protected function getAgentWeights(): array
    {
        $weights = [];
        foreach (array_keys($this->agents) as $type) {
            $predictions = Prediction::where('agent_type', $type)
                ->whereHas('fixture', fn($q) => $q->whereIn('status', ['FT', 'AET', 'PEN']))
                ->with('fixture')
                ->get();

            $total = $predictions->count();
            $correct = 0;

            foreach ($predictions as $pred) {
                $fixture = $pred->fixture;
                if ($fixture->home_score === null) continue;
                $realOutcome = $fixture->home_score > $fixture->away_score ? 'home' :
                    ($fixture->home_score < $fixture->away_score ? 'away' : 'draw');
                $probs = [
                    'home' => $pred->home_probability,
                    'draw' => $pred->draw_probability,
                    'away' => $pred->away_probability,
                ];
                $predictedOutcome = array_search(max($probs), $probs);
                if ($predictedOutcome === $realOutcome) {
                    $correct++;
                }
            }

            $accuracy = $total > 0 ? $correct / $total : 0.2;
            $weights[$type] = max($accuracy, 0.1);
        }

        return $weights;
    }
}