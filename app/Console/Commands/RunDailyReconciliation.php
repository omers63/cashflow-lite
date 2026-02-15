<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ReconciliationService;

class RunDailyReconciliation extends Command
{
    protected $signature = 'reconciliation:run {--type=daily}';
    protected $description = 'Run daily reconciliation';

    public function handle(ReconciliationService $service)
    {
        $this->info('Running daily reconciliation...');

        try {
            $reconciliation = $service->runDailyReconciliation();

            if ($reconciliation->all_passed) {
                $this->info('✓ Reconciliation PASSED - All checks successful');
            } else {
                $this->error('✗ Reconciliation FAILED - ' . $reconciliation->checks_failed . ' checks failed');
                
                foreach ($reconciliation->check_results as $check) {
                    if ($check['status'] === 'FAIL') {
                        $this->line("  - Check #{$check['check_number']}: {$check['name']} (Variance: \${$check['variance']})");
                    }
                }
            }

            return $reconciliation->all_passed ? 0 : 1;

        } catch (\Exception $e) {
            $this->error('Error running reconciliation: ' . $e->getMessage());
            return 1;
        }
    }
}
