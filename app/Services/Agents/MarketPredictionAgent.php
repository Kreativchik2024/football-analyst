<?php

namespace App\Services\Agents;

use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\Odd;

class MarketPredictionAgent
{
    /**
     * Генерирует прогноз на основе рыночных коэффициентов.
     */
    public function predict(Fixture $fixture): ?Prediction
    {
        // Берём лучшие коэффициенты (максимальные по каждому исходу)
        $bestOdds = Odd::where('fixture_id', $fixture->id)
            ->where('market', '1x2')
            ->get()
            ->groupBy('outcome')
            ->map(fn($group) => $group->max('value'));

        if ($bestOdds->count() < 3) {
            return null; // недостаточно данных
        }

        // Вычисляем подразумеваемую вероятность (убираем маржу)
        $totalImplied = 1 / $bestOdds['home'] + 1 / $bestOdds['draw'] + 1 / $bestOdds['away'];
        $homeProb = (1 / $bestOdds['home']) / $totalImplied;
        $drawProb = (1 / $bestOdds['draw']) / $totalImplied;
        $awayProb = (1 / $bestOdds['away']) / $totalImplied;

        return Prediction::updateOrCreate(
            [
                'fixture_id'  => $fixture->id,
                'agent_type'  => 'market',
                'model_version' => '1.0',
            ],
            [
                'home_probability'  => $homeProb,
                'draw_probability'  => $drawProb,
                'away_probability'  => $awayProb,
            ]
        );
    }
}