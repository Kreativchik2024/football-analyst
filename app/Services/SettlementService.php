<?php

namespace App\Services;

use App\Models\UserPrediction;
use App\Models\Fixture;
use App\Models\UserBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettlementService
{
    /**
     * Рассчитать и закрыть ставки после матча
     *
     * @param Fixture $fixture
     * @return int Количество зарегистрированных ставок
     */
    public function settle(Fixture $fixture): int
    {
        // Проверить, что матч завершён
        if (!in_array($fixture->status, ['FT', 'AET', 'PEN'])) {
            Log::info("Fixture {$fixture->id} not finished yet (status: {$fixture->status})");
            return 0;
        }

        // Убедиться, что у матча есть результаты
        if (is_null($fixture->home_score) || is_null($fixture->away_score)) {
            Log::warning("Fixture {$fixture->id} missing score data");
            return 0;
        }

        $predictions = UserPrediction::where('fixture_id', $fixture->id)
            ->where('status', 'pending')
            ->get();

        $settledCount = 0;

        foreach ($predictions as $prediction) {
            try {
                $this->settlePrediction($prediction, $fixture);
                $settledCount++;
            } catch (\Exception $e) {
                Log::error("Settlement failed for prediction {$prediction->id}: {$e->getMessage()}");
                continue;
            }
        }

        Log::info("Settled {$settledCount} predictions for fixture {$fixture->id}");
        return $settledCount;
    }

    /**
     * Рассчитать отдельную ставку
     */
    protected function settlePrediction(UserPrediction $prediction, Fixture $fixture): void
    {
        DB::transaction(function () use ($prediction, $fixture) {
            $outcome = $this->determineOutcome($fixture);
            $won = $prediction->outcome === $outcome;
            
            // Рассчитать прибыль/убыток
            if ($won) {
                $profit = ($prediction->stake * $prediction->odds) - $prediction->stake;
                $status = 'won';
            } else {
                $profit = -$prediction->stake;
                $status = 'lost';
            }

            // Обновить ставку
            $prediction->update([
                'status' => $status,
                'profit' => $profit,
                'settled_at' => now(),
            ]);

            // Обновить баланс пользователя
            $balance = UserBalance::firstOrCreate(
                ['user_id' => $prediction->user_id],
                ['balance' => 100000]
            );
            
            $balance->increment('balance', $profit);

            Log::info("Prediction {$prediction->id} settled: {$status}, profit: {$profit}");
        });
    }

    /**
     * Определить исход матча
     */
    protected function determineOutcome(Fixture $fixture): string
    {
        if ($fixture->home_score > $fixture->away_score) {
            return 'home';
        }
        if ($fixture->home_score < $fixture->away_score) {
            return 'away';
        }
        return 'draw';
    }
}
