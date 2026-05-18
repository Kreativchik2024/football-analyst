<?php
// app/Http/Controllers/MLController.php

namespace App\Http\Controllers;

use App\Models\Fixture;
use App\Models\Team;
use App\Models\Prediction;
use App\Models\Injury;
use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MLController extends Controller
{
    // ========== ОСНОВНЫЕ API МЕТОДЫ ==========

    public function getFeatures(Request $request)
    {
        $request->validate([
            'fixture_ids' => 'sometimes|array',
            'fixture_ids.*' => 'integer|exists:fixtures,id',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
            'limit' => 'sometimes|integer|min=1|max=5000',
        ]);

        $query = Fixture::with(['homeTeam', 'awayTeam', 'league', 'predictions']);

        if ($request->has('fixture_ids')) {
            $query->whereIn('id', $request->fixture_ids);
        } else {
            $dateFrom = $request->get('date_from', Carbon::now()->subMonths(6));
            $dateTo = $request->get('date_to', Carbon::now());
            $query->whereBetween('starting_at', [$dateFrom, $dateTo]);
        }

        if ($request->has('limit')) {
            $query->limit($request->limit);
        }

        $fixtures = $query->orderBy('starting_at')->get();
        $features = [];

        foreach ($fixtures as $fixture) {
            $homeForm = $this->calculateWeightedTeamForm($fixture->homeTeam, $fixture->starting_at);
            $awayForm = $this->calculateWeightedTeamForm($fixture->awayTeam, $fixture->starting_at);
            $headToHead = $this->getHeadToHead($fixture->homeTeam, $fixture->awayTeam, $fixture->starting_at);
            $predictions = $fixture->predictions->where('agent_type', 'api_football')->first();
            $stats = $fixture->matchStatistics->groupBy('stat_type');

            $features[] = [
                'fixture_id' => $fixture->id,
                'external_id' => $fixture->external_id,
                'date' => $fixture->starting_at->toISOString(),
                'home_team_id' => $fixture->home_team_id,
                'away_team_id' => $fixture->away_team_id,
                'home_team_name' => $fixture->homeTeam->name,
                'away_team_name' => $fixture->awayTeam->name,
                'league_id' => $fixture->league_id,
                'league_name' => $fixture->league->name,
                'result' => $this->getResult($fixture),

                'home_form_points' => $homeForm['points_per_game'],
                'away_form_points' => $awayForm['points_per_game'],
                'home_goals_avg' => $homeForm['goals_avg'],
                'away_goals_avg' => $awayForm['goals_avg'],
                'home_clean_sheets_pct' => $homeForm['clean_sheets_pct'],
                'away_clean_sheets_pct' => $awayForm['clean_sheets_pct'],

                'h2h_home_wins' => $headToHead['home_wins'],
                'h2h_draws' => $headToHead['draws'],
                'h2h_away_wins' => $headToHead['away_wins'],
                'h2h_home_goals_avg' => $headToHead['home_goals_avg'],
                'h2h_away_goals_avg' => $headToHead['away_goals_avg'],

                'api_home_prob' => $predictions->home_probability ?? 0.33,
                'api_draw_prob' => $predictions->draw_probability ?? 0.34,
                'api_away_prob' => $predictions->away_probability ?? 0.33,

                'home_xg' => $fixture->home_xg,
                'away_xg' => $fixture->away_xg,
                'home_possession' => $fixture->home_possession,
                'away_possession' => $fixture->away_possession,

                'statistics' => [
                    'shots' => [
                        'home' => $this->getStatValue($stats, 'Total Shots', 'home'),
                        'away' => $this->getStatValue($stats, 'Total Shots', 'away'),
                    ],
                    'shots_on_target' => [
                        'home' => $this->getStatValue($stats, 'Shots on Goal', 'home'),
                        'away' => $this->getStatValue($stats, 'Shots on Goal', 'away'),
                    ],
                    'corners' => [
                        'home' => $this->getStatValue($stats, 'Corner Kicks', 'home'),
                        'away' => $this->getStatValue($stats, 'Corner Kicks', 'away'),
                    ],
                    'fouls' => [
                        'home' => $this->getStatValue($stats, 'Fouls', 'home'),
                        'away' => $this->getStatValue($stats, 'Fouls', 'away'),
                    ],
                    'yellow_cards' => [
                        'home' => $this->getStatValue($stats, 'Yellow Cards', 'home'),
                        'away' => $this->getStatValue($stats, 'Yellow Cards', 'away'),
                    ],
                ],
            ];
        }

        return response()->json(['success' => true, 'count' => count($features), 'features' => $features]);
    }

    public function storePrediction(Request $request)
    {
        $request->validate([
            'predictions' => 'required|array',
            'predictions.*.fixture_id' => 'required|exists:fixtures,id',
            'predictions.*.home_probability' => 'required|numeric|min:0|max:1',
            'predictions.*.draw_probability' => 'required|numeric|min:0|max:1',
            'predictions.*.away_probability' => 'required|numeric|min:0|max:1',
            'predictions.*.agent_type' => 'sometimes|string',
            'predictions.*.model_version' => 'sometimes|string',
        ]);

        $saved = 0;
        $errors = [];

        foreach ($request->predictions as $prediction) {
            try {
                Prediction::updateOrCreate(
                    [
                        'fixture_id' => $prediction['fixture_id'],
                        'agent_type' => $prediction['agent_type'] ?? 'ml_ensemble',
                    ],
                    [
                        'home_probability' => $prediction['home_probability'],
                        'draw_probability' => $prediction['draw_probability'],
                        'away_probability' => $prediction['away_probability'],
                        'model_version' => $prediction['model_version'] ?? 'v1',
                        'features_used' => $prediction['features_used'] ?? null,
                    ]
                );
                $saved++;
            } catch (\Exception $e) {
                $errors[] = ['fixture_id' => $prediction['fixture_id'], 'error' => $e->getMessage()];
            }
        }

        return response()->json(['success' => true, 'saved' => $saved, 'errors' => $errors]);
    }

    /**
     * Данные для быстрого инференса (предстоящие матчи с полным набором признаков)
     */
    public function getUpcomingFeatures(Request $request)
    {
        $days = $request->get('days', 7);
        
        $fixtures = Fixture::with(['homeTeam', 'awayTeam', 'league', 'predictions'])
            ->upcoming()
            ->nextDays($days)
            ->get();

        $features = [];
        foreach ($fixtures as $fixture) {
            $homeForm = $this->calculateWeightedTeamForm($fixture->homeTeam, $fixture->starting_at);
            $awayForm = $this->calculateWeightedTeamForm($fixture->awayTeam, $fixture->starting_at);
            $headToHead = $this->getHeadToHead($fixture->homeTeam, $fixture->awayTeam, $fixture->starting_at);
            $predictions = $fixture->predictions->where('agent_type', 'api_football')->first();

            // Актуальные травмы на дату матча
            $homeInjuries = $this->countActiveInjuries($fixture->homeTeam->id, $fixture->starting_at);
            $awayInjuries = $this->countActiveInjuries($fixture->awayTeam->id, $fixture->starting_at);

            // Средний xG за последние 5 матчей (только завершённые)
            $homeAvgXg = $this->getAverageXg($fixture->homeTeam->id, $fixture->starting_at, 5);
            $awayAvgXg = $this->getAverageXg($fixture->awayTeam->id, $fixture->starting_at, 5);

            $features[] = [
                'fixture_id' => $fixture->id,
                'external_id' => $fixture->external_id,
                'date' => $fixture->starting_at->toISOString(),
                'home_team_name' => $fixture->homeTeam->name,
                'away_team_name' => $fixture->awayTeam->name,
                'league_name' => $fixture->league->name,

                // форма
                'home_form_points' => $homeForm['points_per_game'],
                'away_form_points' => $awayForm['points_per_game'],
                'home_goals_avg' => $homeForm['goals_avg'],
                'away_goals_avg' => $awayForm['goals_avg'],
                'home_clean_sheets_pct' => $homeForm['clean_sheets_pct'],
                'away_clean_sheets_pct' => $awayForm['clean_sheets_pct'],

                // xG история
                'home_avg_xg' => $homeAvgXg,
                'away_avg_xg' => $awayAvgXg,

                // травмы
                'home_injuries' => $homeInjuries,
                'away_injuries' => $awayInjuries,

                // API прогнозы
                'api_home_prob' => $predictions->home_probability ?? 0.33,
                'api_draw_prob' => $predictions->draw_probability ?? 0.34,
                'api_away_prob' => $predictions->away_probability ?? 0.33,

                // H2H
                'h2h_home_wins' => $headToHead['home_wins'],
                'h2h_draws' => $headToHead['draws'],
                'h2h_away_wins' => $headToHead['away_wins'],
            ];
        }

        return response()->json([
            'success' => true,
            'count' => count($features),
            'features' => $features,
        ]);
    }

    /**
     * Обучение модели – возвращает полный набор признаков (включая травмы, xG историю, улучшенную форму)
     */
    public function getTrainingData(Request $request)
    {
        $limit = $request->get('limit', 5000);
        $season = $request->get('season', 2025);

        $fixtures = Fixture::with(['homeTeam', 'awayTeam', 'matchStatistics', 'predictions'])
            ->finished()
            ->whereYear('starting_at', '>=', $season - 1)
            ->limit($limit)
            ->get();

        $data = [];
        foreach ($fixtures as $fixture) {
            $homeForm = $this->calculateWeightedTeamForm($fixture->homeTeam, $fixture->starting_at);
            $awayForm = $this->calculateWeightedTeamForm($fixture->awayTeam, $fixture->starting_at);
            $stats = $fixture->matchStatistics->groupBy('stat_type');

            $homeInjuries = $this->countActiveInjuries($fixture->homeTeam->id, $fixture->starting_at);
            $awayInjuries = $this->countActiveInjuries($fixture->awayTeam->id, $fixture->starting_at);

            $homeAvgXg = $this->getAverageXg($fixture->homeTeam->id, $fixture->starting_at, 5);
            $awayAvgXg = $this->getAverageXg($fixture->awayTeam->id, $fixture->starting_at, 5);

            $headToHead = $this->getHeadToHead($fixture->homeTeam, $fixture->awayTeam, $fixture->starting_at);

            $data[] = [
                'home_elo' => $fixture->homeTeam->elo_rating ?? 1500,
                'away_elo' => $fixture->awayTeam->elo_rating ?? 1500,
                'home_form' => $homeForm['points_per_game'],
                'away_form' => $awayForm['points_per_game'],
                'home_goals_avg' => $homeForm['goals_avg'],
                'away_goals_avg' => $awayForm['goals_avg'],
                'home_clean_sheets_pct' => $homeForm['clean_sheets_pct'],
                'away_clean_sheets_pct' => $awayForm['clean_sheets_pct'],
                'home_injuries' => $homeInjuries,
                'away_injuries' => $awayInjuries,
                'home_avg_xg' => $homeAvgXg,
                'away_avg_xg' => $awayAvgXg,
                'home_xg_match' => $fixture->home_xg ?? 0,
                'away_xg_match' => $fixture->away_xg ?? 0,
                'home_possession' => $fixture->home_possession ?? 50,
                'away_possession' => $fixture->away_possession ?? 50,
                'home_shots' => $this->getStatValue($stats, 'Total Shots', 'home'),
                'away_shots' => $this->getStatValue($stats, 'Total Shots', 'away'),
                'home_shots_on_target' => $this->getStatValue($stats, 'Shots on Goal', 'home'),
                'away_shots_on_target' => $this->getStatValue($stats, 'Shots on Goal', 'away'),
                'api_prob_home' => $fixture->predictions->where('agent_type', 'api_football')->first()->home_probability ?? 0.33,
                'api_prob_draw' => $fixture->predictions->where('agent_type', 'api_football')->first()->draw_probability ?? 0.34,
                'api_prob_away' => $fixture->predictions->where('agent_type', 'api_football')->first()->away_probability ?? 0.33,
                'h2h_home_wins' => $headToHead['home_wins'],
                'h2h_draws' => $headToHead['draws'],
                'h2h_away_wins' => $headToHead['away_wins'],
                'result' => $this->getResult($fixture),
            ];
        }

        return response()->json(['data' => $data]);
    }

    public function status()
    {
        $lastPredictions = Prediction::where('agent_type', 'ml_ensemble')
            ->orderBy('created_at', 'desc')
            ->first();

        $upcomingCount = Fixture::upcoming()->count();
        $hasPredictions = Prediction::where('agent_type', 'ml_ensemble')
            ->whereHas('fixture', fn($q) => $q->upcoming())
            ->exists();

        return response()->json([
            'status' => 'ok',
            'last_prediction' => $lastPredictions?->created_at,
            'upcoming_matches' => $upcomingCount,
            'has_upcoming_predictions' => $hasPredictions,
            'models_ready' => true,
        ]);
    }

    // ========== НОВЫЕ / ДОПОЛНИТЕЛЬНЫЕ МЕТОДЫ ==========

    /**
     * Взвешенная форма команды (последние матчи с экспоненциальными весами)
     */
    private function calculateWeightedTeamForm(Team $team, Carbon $beforeDate, int $matchesCount = 5): array
    {
        $fixtures = Fixture::where('starting_at', '<', $beforeDate)
            ->where(fn($q) => $q->where('home_team_id', $team->id)->orWhere('away_team_id', $team->id))
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->orderBy('starting_at', 'desc')
            ->limit($matchesCount)
            ->get();

        if ($fixtures->isEmpty()) {
            return [
                'points_per_game' => 1.5,
                'goals_avg' => 1.0,
                'clean_sheets_pct' => 0.2,
                'matches_count' => 0,
            ];
        }

        $totalWeight = 0;
        $weights = [];
        for ($i = 0; $i < $fixtures->count(); $i++) {
            $weight = 1.0 / ($i + 1);
            $weights[$i] = $weight;
            $totalWeight += $weight;
        }

        $points = 0;
        $goals = 0;
        $cleanSheets = 0;
        $idx = 0;
        foreach ($fixtures as $fixture) {
            $w = $weights[$idx];
            $isHome = $fixture->home_team_id == $team->id;
            $goalsScored = $isHome ? $fixture->home_score : $fixture->away_score;
            $goalsConceded = $isHome ? $fixture->away_score : $fixture->home_score;

            $goals += $goalsScored * $w;
            if ($goalsConceded == 0) $cleanSheets += $w;

            if ($goalsScored > $goalsConceded) $points += 3 * $w;
            elseif ($goalsScored == $goalsConceded) $points += 1 * $w;
            $idx++;
        }

        return [
            'points_per_game' => round($points / $totalWeight, 2),
            'goals_avg' => round($goals / $totalWeight, 2),
            'clean_sheets_pct' => round($cleanSheets / $totalWeight, 2),
            'matches_count' => $fixtures->count(),
        ];
    }

    /**
     * Количество активных травм на дату матча
     */
    private function countActiveInjuries(int $teamId, Carbon $matchDate): int
    {
        return Injury::where('team_id', $teamId)
            ->where('start_date', '<=', $matchDate)
            ->where(fn($q) => $q->where('end_date', '>=', $matchDate)->orWhereNull('end_date'))
            ->count();
    }

    /**
     * Средний xG команды за последние N матчей до указанной даты
     */
    private function getAverageXg(int $teamId, Carbon $beforeDate, int $matches = 5): float
    {
        $fixtures = Fixture::where('starting_at', '<', $beforeDate)
            ->where(fn($q) => $q->where('home_team_id', $teamId)->orWhere('away_team_id', $teamId))
            ->whereNotNull('home_xg')
            ->whereNotNull('away_xg')
            ->orderBy('starting_at', 'desc')
            ->limit($matches)
            ->get();

        if ($fixtures->isEmpty()) return 1.0;

        $totalXg = 0;
        foreach ($fixtures as $fixture) {
            $isHome = $fixture->home_team_id == $teamId;
            $totalXg += $isHome ? $fixture->home_xg : $fixture->away_xg;
        }
        return round($totalXg / $fixtures->count(), 2);
    }

    /**
     * Исход матча (0 – хозяева, 1 – ничья, 2 – гости)
     */
    private function getResult(Fixture $fixture): ?int
    {
        if ($fixture->home_score === null || $fixture->away_score === null) return null;
        if ($fixture->home_score > $fixture->away_score) return 0;
        if ($fixture->home_score == $fixture->away_score) return 1;
        return 2;
    }

    /**
     * Получить значение статистики из коллекции
     */
    private function getStatValue($statsCollection, string $statType, string $side): ?float
    {
        $stat = $statsCollection->get($statType);
        if (!$stat || $stat->isEmpty()) return null;
        return $side === 'home' ? $stat->first()->home_value : $stat->first()->away_value;
    }

    // Старый метод getHeadToHead (оставляем без изменений, он уже есть)
    private function getHeadToHead(Team $homeTeam, Team $awayTeam, Carbon $beforeDate): array
    {
        $fixtures = Fixture::where('starting_at', '<', $beforeDate)
            ->where(fn($q) => $q->where(fn($sq) => $sq->where('home_team_id', $homeTeam->id)->where('away_team_id', $awayTeam->id))
                ->orWhere(fn($sq) => $sq->where('home_team_id', $awayTeam->id)->where('away_team_id', $homeTeam->id)))
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->limit(10)
            ->get();

        $homeWins = 0;
        $draws = 0;
        $awayWins = 0;
        $homeGoals = 0;
        $awayGoals = 0;

        foreach ($fixtures as $fixture) {
            $isHomeTeamAtHome = $fixture->home_team_id == $homeTeam->id;
            $homeTeamGoals = $isHomeTeamAtHome ? $fixture->home_score : $fixture->away_score;
            $awayTeamGoals = $isHomeTeamAtHome ? $fixture->away_score : $fixture->home_score;

            $homeGoals += $homeTeamGoals;
            $awayGoals += $awayTeamGoals;

            if ($homeTeamGoals > $awayTeamGoals) $homeWins++;
            elseif ($homeTeamGoals < $awayTeamGoals) $awayWins++;
            else $draws++;
        }

        $total = max($fixtures->count(), 1);
        return [
            'home_wins' => round($homeWins / $total, 2),
            'draws' => round($draws / $total, 2),
            'away_wins' => round($awayWins / $total, 2),
            'home_goals_avg' => round($homeGoals / $total, 2),
            'away_goals_avg' => round($awayGoals / $total, 2),
            'matches_played' => $fixtures->count(),
        ];
    }
}