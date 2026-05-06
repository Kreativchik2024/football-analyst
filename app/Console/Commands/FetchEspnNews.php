<?php

namespace App\Console\Commands;

use App\Models\News;
use App\Services\EspnService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchEspnNews extends Command
{
    protected $signature = 'fetch:espn-news 
                            {--league= : Короткий код лиги/турнира}
                            {--all-soccer : Получить новости для всех популярных лиг и турниров}
                            {--limit=25 : Количество новостей за запрос}';

    protected $description = 'Получить футбольные новости из ESPN API и сохранить в БД';

    protected array $mainSoccerLeagues = [
        'eng.1', 'esp.1', 'ger.1', 'ita.1', 'fra.1',
        'uefa.champions', 'uefa.europa', 'uefa.europa.conf',
        'fifa.world', 'eng.fa', 'esp.copa_del_rey',
    ];

    public function handle(EspnService $espn): void
    {
        // Временно включаем нужную локаль для перевода (например, русскую)
        $originalLocale = app()->getLocale();
        if ($originalLocale !== 'ru') {
            app()->setLocale('ru');
        }

        $limit = (int) $this->option('limit');
        $leagueOption = $this->option('league');
        $allSoccer = $this->option('all-soccer');

        if ($leagueOption) {
            $this->info("Fetching news for league: {$leagueOption}");
            $newsItems = $espn->getFootballNews($leagueOption, $limit);
            $saved = $this->saveNews($newsItems, $leagueOption);
            $this->info("Saved {$saved} news items.");
        } elseif ($allSoccer) {
            $totalSaved = 0;
            foreach ($this->mainSoccerLeagues as $slug) {
                $this->info("Fetching news for: {$slug}");
                $newsItems = $espn->getFootballNews($slug, $limit);
                $totalSaved += $this->saveNews($newsItems, $slug);
                usleep(300000);
            }
            $this->info("Total saved: {$totalSaved} news items.");
        } else {
            $this->info("Fetching general soccer news...");
            $newsItems = $espn->getFootballNews(null, $limit);
            $saved = $this->saveNews($newsItems);
            $this->info("Saved {$saved} news items.");
        }

        // Возвращаем локаль обратно
        app()->setLocale($originalLocale);
    }

    /**
     * Сохраняет новости в базу данных.
     */
    protected function saveNews(array $newsItems, ?string $leagueSlug = null): int
    {
        $savedCount = 0;

        foreach ($newsItems as $item) {
            try {
                $title = $item['headline'] ?? $item['title'] ?? 'Без заголовка';
                $url = $item['links']['web']['href'] ?? $item['links']['mobile']['href'] ?? null;
                if (!$url) continue;

                $imageUrl = null;
                if (!empty($item['images']) && isset($item['images'][0]['url'])) {
                    $imageUrl = $item['images'][0]['url'];
                }

                $publishedDate = $item['published'] ?? $item['lastModified'] ?? now();
                $publishedAt = Carbon::parse($publishedDate)->format('Y-m-d H:i:s');

                $source = $item['byline'] ?? 'ESPN';

                if (News::where('url', $url)->exists()) continue;

                News::create([
                    'title'        => $title,
                    'content'      => $item['description'] ?? $item['summary'] ?? null,
                    'url'          => $url,
                    'image_url'    => $imageUrl,
                    'source'       => $source,
                    'league_slug'  => $leagueSlug,
                    'published_at' => $publishedAt,
                ]);

                $savedCount++;
            } catch (\Exception $e) {
                $this->warn("  ⚠️ Failed to save news item: " . $e->getMessage());
            }
        }

        return $savedCount;
    }
}