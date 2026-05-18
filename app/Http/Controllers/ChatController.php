<?php

namespace App\Http\Controllers;

use App\Models\Fixture;
use App\Models\ValueBet;
use App\Models\News;
use App\Models\DailyBriefing;
// use App\Services\DeepSeekService;
use Illuminate\Http\Request;
use App\Services\LocalDeepSeekService;

class ChatController extends Controller
{
    public function ask(Request $request, LocalDeepSeekService $deepseek)
    {
        $question = $request->input('question');
        if (!$question) {
            return response()->json(['error' => 'Вопрос не задан'], 422);
        }

        $todayContext = $this->getTodayContext();

        $systemPrompt = "Ты — AI-ассистент платформы DeepOdds, футбольный аналитик. "
                      . "Отвечай на русском языке, кратко и по делу. "
                      . "Используй предоставленный контекст о предстоящих матчах, лучших ставках и новостях.";

         $answer = $deepseek->ask($systemPrompt, $todayContext . "\n\nВопрос пользователя: " . $question);

        return response()->json(['answer' => $answer ?? 'Извините, я затрудняюсь ответить.']);
    }

    protected function getTodayContext(): string
    {
        $briefing = DailyBriefing::where('briefing_date', today())->first();
        if ($briefing) return $briefing->content;

        $upcomingFixtures = Fixture::with(['homeTeam', 'awayTeam'])
            ->where('starting_at', '>=', now())
            ->whereIn('status', ['NS', 'TBD', 'PST'])
            ->orderBy('starting_at')
            ->limit(5)
            ->get();

        $topBets = ValueBet::with('fixture.homeTeam', 'fixture.awayTeam', 'odd')
            ->where('type', 'prematch')
            ->where('status', 'pending')
            ->orderBy('expected_value', 'desc')
            ->limit(5)
            ->get();

        $latestNews = News::latest('published_at')->limit(3)->get();

        $context = "Сегодня " . today()->format('d.m.Y') . ".\n\n";
        $context .= "Предстоящие матчи:\n";
        foreach ($upcomingFixtures as $f) {
            $context .= "- {$f->homeTeam->name} vs {$f->awayTeam->name}, начало в " . $f->starting_at->format('H:i') . "\n";
        }
        $context .= "\nЛучшие ставки на сегодня:\n";
        foreach ($topBets as $bet) {
            $context .= "- {$bet->fixture->homeTeam->name} vs {$bet->fixture->awayTeam->name}: {$bet->bet_type} за {$bet->odd->value} (EV: {$bet->expected_value})\n";
        }
        $context .= "\nПоследние новости:\n";
        foreach ($latestNews as $n) {
            $context .= "- {$n->title}\n";
        }
        return $context;
    }
}