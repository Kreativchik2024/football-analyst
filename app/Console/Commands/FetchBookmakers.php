<?php

namespace App\Console\Commands;

use App\Models\Bookmaker;
use App\Services\ApiFootballService;
use Illuminate\Console\Command;

class FetchBookmakers extends Command
{
    protected $signature = 'fetch:bookmakers';
    protected $description = 'Fetch bookmakers list and set country for Russian ones';

    protected ApiFootballService $api;

    protected array $russianBookmakers = [
        '1xBet', 'Fonbet', 'Winline', 'BetBoom', 'Melbet',
        'Pari', 'Liga Stavok', 'Betcity', 'Tennisi', 'Zenit',
        'Paribet', 'Bettery', 'Leon', 'Marathon', 'Olimp',
    ];

    public function __construct(ApiFootballService $api)
    {
        parent::__construct();
        $this->api = $api;
    }

    public function handle()
    {
        $data = $this->api->getBookmakers();
        if (!$data) {
            $this->error('Failed to fetch bookmakers');
            return;
        }

        $bookmakers = $data['response'] ?? [];
        $russianCount = 0;

        foreach ($bookmakers as $item) {
            $name = $item['name'] ?? null;
            if (empty($name)) {
                continue; // пропускаем букмекеров без имени
            }

            $isRussian = $this->isRussian($name);

            Bookmaker::updateOrCreate(
                ['external_id' => $item['id']],
                [
                    'name'    => $name,
                    'country' => $isRussian ? 'Russia' : null,
                ]
            );

            if ($isRussian) {
                $this->info("✓ {$name} — Russia");
                $russianCount++;
            }
        }

        $this->info("✅ Done. Found {$russianCount} Russian bookmakers.");
    }

    protected function isRussian(string $name): bool
    {
        foreach ($this->russianBookmakers as $russianName) {
            if (stripos($name, $russianName) !== false) {
                return true;
            }
        }
        return false;
    }
}