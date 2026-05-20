<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class RefreshRecentMatches extends Command
{
    protected $signature = 'matches:refresh {--hours=24 : Количество часов назад от текущего момента}';
    protected $description = 'Обновить матчи за последние N часов (без коэффициентов)';

    public function handle()
    {
        $hours = (int) $this->option('hours');
        $from = Carbon::now()->subHours($hours)->toDateString();
        $to = Carbon::now()->toDateString();
        
        $this->info("Обновление матчей с {$from} по {$to} (последние {$hours} часов)");
        
        // Запускаем fetch:season с пропуском коэффициентов
        $exitCode = Artisan::call('fetch:season', [
            'season' => Carbon::now()->year,
            '--from' => $from,
            '--to' => $to,
            '--skip-odds' => true,
            '--skip-events' => false,   // события можно обновить
            '--skip-statistics' => false, // статистику тоже нужно
            '--delay' => 250,
            '--limit' => 2000,
        ]);
        
        $output = Artisan::output();
        $this->info($output);
        
        if ($exitCode === 0) {
            $this->info("Матчи успешно обновлены.");
        } else {
            $this->error("Ошибка при обновлении матчей.");
        }
        
        return $exitCode;
    }
}