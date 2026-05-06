<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EspnService
{
    protected string $baseUrl = 'https://now.core.api.espn.com/v1/sports/news';

    /**
     * Получить футбольные новости с переводом на текущий язык приложения.
     */
    public function getFootballNews(?string $leagueSlug = null, int $limit = 25): array
    {
        $params = [
            'sport' => 'soccer',
            'limit' => $limit,
        ];

        if ($leagueSlug) {
            $params['league'] = $leagueSlug;
        }

        try {
            $response = Http::timeout(15)
                ->retry(3, 2000)
                ->get($this->baseUrl, $params);

            if ($response->successful()) {
                $data = $response->json();
                $headlines = $data['headlines'] ?? [];

                // Если текущая локаль не английская — переводим
                $locale = app()->getLocale();
                if ($locale !== 'en') {
                    foreach ($headlines as &$item) {
                        if (isset($item['headline'])) {
                            $item['headline'] = $this->translateAuto($item['headline'], $locale);
                        }
                        if (isset($item['description'])) {
                            $item['description'] = $this->translateAuto($item['description'], $locale);
                        }
                        if (isset($item['title'])) {
                            $item['title'] = $this->translateAuto($item['title'], $locale);
                        }
                    }
                }

                return $headlines;
            }

            Log::warning('ESPN API returned status: ' . $response->status());
        } catch (\Exception $e) {
            Log::error('ESPN API request failed: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Перевести текст на указанный язык через MyMemory (бесплатно, без API-ключа).
     */
    protected function translateAuto(string $text, string $targetLang): string
    {
        if (empty($text) || $targetLang === 'en') {
            return $text;
        }

        try {
            $response = Http::timeout(5)
                ->get('https://api.mymemory.translated.net/get', [
                    'q' => $text,
                    'langpair' => 'en|' . $targetLang,
                ]);

            if ($response->successful()) {
                $translated = $response->json('responseData.translatedText');
                if ($translated) {
                    return $translated;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Translation failed: ' . $e->getMessage());
        }

        return $text;
    }
}