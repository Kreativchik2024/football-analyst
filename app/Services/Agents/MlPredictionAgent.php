<?php

namespace App\Services\Agents;

use App\Models\Fixture;
use App\Models\Prediction;
use Illuminate\Support\Facades\Http;

class MlPredictionAgent
{
    protected string $apiUrl = 'http://localhost:8000/predict';

    public function predict(Fixture $fixture): ?Prediction
    {
        $stats = $fixture->matchStatistics->keyBy('stat_type');

        $response = Http::post($this->apiUrl, [
            'home_xg' => $fixture->home_xg ?? 0,
            'away_xg' => $fixture->away_xg ?? 0,
            'home_possession' => $fixture->home_possession ?? 0,
            'away_possession' => $fixture->away_possession ?? 0,
            'home_shots' => $stats['Total Shots']->home_value ?? 0,
            'away_shots' => $stats['Total Shots']->away_value ?? 0,
            'home_shots_on_target' => $stats['Shots on Goal']->home_value ?? 0,
            'away_shots_on_target' => $stats['Shots on Goal']->away_value ?? 0,
            'home_corners' => $stats['Corner Kicks']->home_value ?? 0,
            'away_corners' => $stats['Corner Kicks']->away_value ?? 0,
            'home_fouls' => $stats['Fouls']->home_value ?? 0,
            'away_fouls' => $stats['Fouls']->away_value ?? 0,
            'home_offsides' => $stats['Offsides']->home_value ?? 0,
            'away_offsides' => $stats['Offsides']->away_value ?? 0,
            'home_passes' => $stats['Total Passes']->home_value ?? 0,
            'away_passes' => $stats['Total Passes']->away_value ?? 0,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return Prediction::updateOrCreate(
                [
                    'fixture_id' => $fixture->id,
                    'agent_type' => 'ml_model',
                    'model_version' => 'xgb_v1',
                ],
                [
                    'home_probability' => $data['home_probability'],
                    'draw_probability' => $data['draw_probability'],
                    'away_probability' => $data['away_probability'],
                ]
            );
        }

        return null;
    }
}