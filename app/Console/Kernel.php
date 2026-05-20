<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Загрузка новостей ESPN каждый час
        $schedule->command('fetch:espn-news')->hourly();
        
        // Ежедневная сводка ассистента
        $schedule->command('briefing:generate')->dailyAt('08:00');
         $schedule->command('matches:refresh --hours=1')->everyThirtyMinutes();
           // Загрузка предстоящих матчей на 7 дней, раз в сутки в 3:00
    $schedule->command('fetch:upcoming --days=7 --skip-odds')->dailyAt('03:00');
    
    // Также можно обновлять прогнозы API‑Football (если нужны)
    $schedule->command('fetch:predictions --days=7 --update-existing')->dailyAt('04:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        // Автоматически загружаем все команды из папки Commands
        $this->load(__DIR__.'/Commands');

        // Подключаем консольные маршруты (если есть)
        require base_path('routes/console.php');
    }
    protected $commands = [
    \App\Console\Commands\DataAudit::class,
];
}