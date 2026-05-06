<?php

namespace App\Actions;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InvalidOddsException;
use App\Models\Fixture;
use App\Models\UserBalance;
use App\Models\UserPrediction;
use Illuminate\Support\Facades\DB;

class PlaceBetAction
{
    /**
     * Разместить ставку пользователя
     *
     * @param int $userId
     * @param int $fixtureId
     * @param string $market
     * @param string $outcome
     * @param float $stake
     * @return UserPrediction
     *
     * @throws InsufficientBalanceException
     * @throws InvalidOddsException
     */
    public function execute(
        int $userId,
        int $fixtureId,
        string $market,
        string $outcome,
        float $stake
    ): UserPrediction {
        return DB::transaction(function () use ($userId, $fixtureId, $market, $outcome, $stake) {
            // Получить матч
            $fixture = Fixture::findOrFail($fixtureId);

            // Получить коэффициент
            $odds = $fixture->odds()
                ->where('market', $market)
                ->where('outcome', $outcome)
                ->value('value');

            if (!$odds) {
                throw new InvalidOddsException();
            }

            // Получить или создать баланс пользователя
            $userBalance = UserBalance::firstOrCreate(
                ['user_id' => $userId],
                ['balance' => 100000]
            );

            // Проверить достаточность средств
            if ($userBalance->balance < $stake) {
                throw new InsufficientBalanceException();
            }

            // Снять средства
            $userBalance->decrement('balance', $stake);

            // Создать ставку
            return UserPrediction::create([
                'user_id'    => $userId,
                'fixture_id' => $fixtureId,
                'market'     => $market,
                'outcome'    => $outcome,
                'stake'      => $stake,
                'odds'       => $odds,
                'status'     => 'pending',
            ]);
        });
    }
}
