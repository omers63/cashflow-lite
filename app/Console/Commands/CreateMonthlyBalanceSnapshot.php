<?php

namespace App\Console\Commands;

use App\Services\ReconciliationService;
use Illuminate\Console\Command;

class CreateMonthlyBalanceSnapshot extends Command
{
    protected $signature = 'snapshots:monthly {--year= : Year (default: previous month)} {--month= : Month (default: previous month)}';

    protected $description = 'Create a monthly balance snapshot for the given or previous month';

    public function handle(): int
    {
        $service = app(ReconciliationService::class);
        $year = $this->option('year') ? (int) $this->option('year') : null;
        $month = $this->option('month') ? (int) $this->option('month') : null;

        if (($year !== null && $month === null) || ($year === null && $month !== null)) {
            $this->error('Provide both --year and --month, or omit both for previous month.');
            return 1;
        }

        $snapshot = $service->createMonthlyBalanceSnapshot($year, $month);
        $this->info('Created balance snapshot for ' . $snapshot->snapshot_date->format('Y-m-d') . ' (period: ' . $snapshot->period . ').');
        return 0;
    }
}
