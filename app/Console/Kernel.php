<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Services\ReconciliationService;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Daily reconciliation at 11:30 PM
        $schedule->call(function () {
            app(ReconciliationService::class)->runDailyReconciliation();
        })->dailyAt('23:30');

        // Check exception SLAs hourly (mark breached)
        $schedule->command('exceptions:check-sla')->hourly();

        // Monthly balance snapshot on the 1st at 00:15 (for previous month)
        $schedule->command('snapshots:monthly')->monthlyOn(1, '00:15');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
