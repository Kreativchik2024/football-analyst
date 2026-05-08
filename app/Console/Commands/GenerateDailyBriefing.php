<?php

namespace App\Console\Commands;

use App\Models\Fixture;
use App\Models\ValueBet;
use App\Models\News;
use App\Models\DailyBriefing;
use App\Services\DeepSeekService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class GenerateDailyBriefing extends Command
{
    protected $signature = 'briefing:generate';
    protected $description = 'Генерирует ежедневную сводку через DeepSeek и сохраняет в БД';

    public function handle(DeepSeekService $deepseek)
    {
        $today = Carbon::today()->format('d.m.Y');
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

        $latestNews = News::latest('published_at')->limit(5)->get();

        $prompt = $this->buildContextString($today, $upcomingFixtures, $topBets, $latestNews);

        $systemPrompt = "Ты — футбольный аналитик и помощник. На основе предоставленных данных напиши ободряющую и информативную сводку на предстоящий день (объемом не более 500 символов). Упомяни самые интересные матчи и лучшие ставки.";

        $briefing = $deepseek->ask($systemPrompt, $prompt);

        if ($briefing) {
            DailyBriefing::updateOrCreate(
                ['briefing_date' => Carbon::today()],
                ['content' => $briefing]
            );
            $this->info('Ежедневная сводка сгенерирована.');
        } else {
            $this->error('Не удалось получить ответ от DeepSeek.');
        }
    }

    protected function buildContextString(string $today, $fixtures, $bets, $news): string
    {
        $context = "Сегодня {$today}.\n\n";
        $context .= "Предстоящие матчи:\n";
        foreach ($fixtures as $f) {
            $context .= "- {$f->homeTeam->name} vs {$f->awayTeam->name}, начало в " . $f->starting_at->format('H:i') . "\n";
        }
        $context .= "\nЛучшие ставки на сегодня:\n";
        foreach ($bets as $bet) {
            $context .= "- {$bet->fixture->homeTeam->name} vs {$bet->fixture->awayTeam->name}: {$bet->bet_type} за {$bet->odd->value} (EV: {$bet->expected_value})\n";
        }
        $context .= "\nПоследние новости:\n";
        foreach ($news as $n) {
            $context .= "- {$n->title}\n";
        }
        return $context;
    }
}