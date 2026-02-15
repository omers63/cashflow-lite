<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LoanService;
use App\Models\Loan;

class CheckDelinquentLoans extends Command
{
    protected $signature = 'loans:check-delinquency';
    protected $description = 'Check for delinquent loans and send notifications';

    public function handle(LoanService $loanService)
    {
        $delinquentLoans = $loanService->getDelinquentLoans();

        if ($delinquentLoans->isEmpty()) {
            $this->info('No delinquent loans found.');
            return 0;
        }

        $this->info("Found {$delinquentLoans->count()} delinquent loan(s):");

        foreach ($delinquentLoans as $loan) {
            $this->line("- Loan {$loan->loan_id} (User: {$loan->user->name}) - {$loan->days_overdue} days overdue");
            
            // Here you would send notifications
            // Notification::send($loan->user, new LoanDelinquentNotification($loan));
        }

        return 0;
    }
}
