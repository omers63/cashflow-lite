<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LoanService;

class SendPaymentReminders extends Command
{
    protected $signature = 'loans:payment-reminders';
    protected $description = 'Send payment reminders for upcoming loan payments';

    public function handle(LoanService $loanService)
    {
        $loansDueSoon = $loanService->getLoansDueSoon(7);

        if ($loansDueSoon->isEmpty()) {
            $this->info('No loans due in the next 7 days.');
            return 0;
        }

        $this->info("Found {$loansDueSoon->count()} loan(s) due soon:");

        foreach ($loansDueSoon as $loan) {
            $daysUntilDue = now()->diffInDays($loan->next_payment_date);
            $this->line("- Loan {$loan->loan_id} (User: {$loan->user->name}) - Due in {$daysUntilDue} days");
            
            // Here you would send reminders
            // Notification::send($loan->user, new PaymentReminderNotification($loan));
        }

        return 0;
    }
}
