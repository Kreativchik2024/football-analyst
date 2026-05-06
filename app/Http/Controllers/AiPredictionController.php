<?php

namespace App\Http\Controllers;

use App\Models\Fixture;
use App\Models\Prediction;
use Illuminate\Http\Request;

class AiPredictionController extends Controller
{
    public function index(Request $request)
    {
        $upcomingFixtures = Fixture::with(['homeTeam', 'awayTeam', 'predictions', 'ensemblePrediction'])
            ->where('starting_at', '>=', now())
            ->whereIn('status', ['NS', 'TBD', 'PST'])
            ->where('starting_at', '<=', now()->addDays(7))
            ->orderBy('starting_at')
            ->get();

        $agentStats = $this->getAgentStatistics();

        return view('ai.predictions', compact('upcomingFixtures', 'agentStats'));
    }

    protected function getAgentStatistics(): array
    {
        $completedPredictions = Prediction::whereHas('fixture', function ($q) {
            $q->whereIn('status', ['FT', 'AET', 'PEN']);
        })->with('fixture')->get();

        $agents = ['api_football', 'market', 'ml_model', 'sarimax', 'openai_news'];
        $stats = [];

        foreach ($agents as $agent) {
            $agentPredictions = $completedPredictions->where('agent_type', $agent);
            $total = $agentPredictions->count();
            $correct = 0;

            foreach ($agentPredictions as $pred) {
                $fixture = $pred->fixture;
                if (!$fixture || $fixture->home_score === null) continue;

                $realOutcome = $fixture->home_score > $fixture->away_score ? 'home' :
                    ($fixture->home_score < $fixture->away_score ? 'away' : 'draw');

                $probs = [
                    'home' => $pred->home_probability,
                    'draw' => $pred->draw_probability,
                    'away' => $pred->away_probability,
                ];
                $predictedOutcome = array_keys($probs, max($probs))[0];

                if ($predictedOutcome === $realOutcome) {
                    $correct++;
                }
            }

            $stats[$agent] = [
                'total'   => $total,
                'correct' => $correct,
                'accuracy' => $total > 0 ? round(($correct / $total) * 100, 1) : 0,
            ];
        }

        return $stats;
    }
}