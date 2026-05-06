<?php

namespace App\Services\Agents;

use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\News;
use App\Services\OpenAiService;
use Illuminate\Support\Facades\Log;

class NewsPredictionAgent
{
    protected OpenAiService $openai;

    public function __construct(OpenAiService $openai)
    {
        $this->openai = $openai;
    }

    public function predict(Fixture $fixture): ?Prediction
    {
        // 1. Собираем последние новости, связанные с командами
        $relevantNews = News::where(function ($query) use ($fixture) {
                $query->where('title', 'like', '%' . $fixture->homeTeam->name . '%')
                      ->orWhere('title', 'like', '%' . $fixture->awayTeam->name . '%')
                      ->orWhere('content', 'like', '%' . $fixture->homeTeam->name . '%')
                      ->orWhere('content', 'like', '%' . $fixture->awayTeam->name . '%');
            })
            ->where('published_at', '>=', now()->subHours(48))
            ->latest()
            ->limit(5)
            ->get();

        // 2. Формируем контекст
        $newsText = "";
        foreach ($relevantNews as $news) {
            $newsText .= "- " . $news->title . ": " . strip_tags($news->content) . "\n";
        }

        if (empty($newsText)) {
            $newsText = "Нет свежих новостей по этим командам.";
        }

        // 3. Системный промпт
        $systemPrompt = <<<PROMPT
Ты — футбольный аналитик, который делает прогнозы на основе новостей. 
Твоя задача — проанализировать предоставленные новости и выдать вероятности исходов футбольного матча в процентах (сумма должна быть равна 100%). 
Возвращай строго JSON в формате: 
{
  "home_win_percent": 0,
  "draw_percent": 0,
  "away_win_percent": 0,
  "rationale": "краткое пояснение"
}
Не добавляй лишних текстов.
PROMPT;

        // 4. Пользовательский промпт
        $userPrompt = "Сделай прогноз на матч: {$fixture->homeTeam->name} против {$fixture->awayTeam->name}.\n\n";
        $userPrompt .= "Последние новости:\n" . $newsText;

        // 5. Запрос к OpenAI
        $responseText = $this->openai->ask($systemPrompt, $userPrompt);

        if (!$responseText) {
            Log::warning('NewsPredictionAgent: пустой ответ от OpenAI');
            return null;
        }

        // 6. Парсинг JSON-ответа
        $data = json_decode($responseText, true);
        if (!$data || !isset($data['home_win_percent'])) {
            Log::warning('NewsPredictionAgent: невалидный JSON от OpenAI', ['response' => $responseText]);
            return null;
        }

        // 7. Сохраняем прогноз
        return Prediction::updateOrCreate(
            [
                'fixture_id'  => $fixture->id,
                'agent_type'  => 'openai_news',
                'model_version' => 'gpt-3.5-turbo',
            ],
            [
                'home_probability'  => $data['home_win_percent'] / 100,
                'draw_probability'  => $data['draw_percent'] / 100,
                'away_probability'  => $data['away_win_percent'] / 100,
                'features_used'     => json_encode([
                    'rationale' => $data['rationale'] ?? '',
                    'news_count' => $relevantNews->count(),
                ]),
            ]
        );
    }
}