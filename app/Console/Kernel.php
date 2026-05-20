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
        // Новости ESPN каждый час
        $schedule->command('fetch:espn-news --all-soccer')->hourly();

        // Предстоящие матчи на 7 дней, раз в сутки в 3:00
        $schedule->command('fetch:upcoming --days=7 --skip-odds')->dailyAt('03:00');

        // Прогнозы API‑Football раз в сутки в 4:00
        $schedule->command('fetch:predictions --days=7 --update-existing')->dailyAt('04:00');

        // Дозагрузка статистики каждые 30 минут
        $schedule->command('fetch:missing-details --limit=50')->everyThirtyMinutes();

        // (Опционально) Ежедневная сводка ассистента
        // $schedule->command('briefing:generate')->dailyAt('08:00');

        // Обновление матчей каждые 30 минут
         $schedule->command('matches:recent-hour --skip-odds')
             ->hourly()
             ->withoutOverlapping()
             ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}