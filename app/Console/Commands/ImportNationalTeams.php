<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\ApiFootballService;
use Illuminate\Console\Command;

class ImportNationalTeams extends Command
{
    protected $signature = 'import:national-teams';
    protected $description = 'Импорт всех национальных сборных из API (без кэша)';

    protected ApiFootballService $api;

    public function __construct(ApiFootballService $api)
    {
        parent::__construct();
        $this->api = $api;
    }

    public function handle()
    {
        $this->info('Импорт национальных сборных...');
        $page = 1;
        $imported = 0;
        $totalTeams = 0;

        while (true) {
            $this->line("Загрузка страницы {$page}...");
            $response = $this->api->getTeamsNoCache(['page' => $page]);
            if (!$response->successful()) {
                $this->error("Ошибка API на странице {$page}: " . $response->status());
                break;
            }
            $data = $response->json();
            $teams = $data['response'] ?? [];
            $paging = $data['paging'] ?? [];
            $totalPages = $paging['total'] ?? 0;

            if (empty($teams)) {
                $this->line("Страница {$page} не содержит команд, завершаем.");
                break;
            }

            $this->line("Получено команд: " . count($teams));
            $totalTeams += count($teams);

            foreach ($teams as $teamData) {
                // ВАЖНО: выводим структуру для первой команды, чтобы понять, где поле national
                if ($totalTeams == 1) {
                    $this->line("Пример структуры: " . json_encode($teamData));
                }

                // Пытаемся определить национальную сборную
                $isNational = $teamData['team']['national'] ?? false;
                // Если поле national отсутствует, можно определить по типу лиги или по country
                if (!$isNational) {
                    // Альтернативный признак: если страна команды совпадает с названием команды (вероятно, сборная)
                    $teamName = $teamData['team']['name'];
                    $country = $teamData['country']['name'] ?? '';
                    if (stripos($teamName, 'national') !== false || stripos($teamName, $country) !== false) {
                        $isNational = true;
                    }
                }

                if ($isNational) {
                    Team::updateOrCreate(
                        ['external_id' => $teamData['team']['id']],
                        [
                            'name'       => $teamData['team']['name'],
                            'country'    => $teamData['country']['name'] ?? null,
                            'logo_url'   => $teamData['team']['logo'] ?? null,
                            'short_code' => $teamData['team']['code'] ?? null,
                        ]
                    );
                    $imported++;
                    $this->line("Импортирована сборная: {$teamData['team']['name']}");
                }
            }

            $page++;
            if ($totalPages > 0 && $page > $totalPages) break;
            if (count($teams) == 0) break;
            usleep(200000);
        }

        $this->info("✅ Импорт завершён. Всего обработано команд: {$totalTeams}, импортировано сборных: {$imported}");
        return 0;
    }
}