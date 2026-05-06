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
}