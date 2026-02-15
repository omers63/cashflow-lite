<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Exception;

class CheckExceptionSLA extends Command
{
    protected $signature = 'exceptions:check-sla';
    protected $description = 'Check for exceptions that have breached their SLA';

    public function handle()
    {
        $overdueExceptions = Exception::overdue()->get();

        if ($overdueExceptions->isEmpty()) {
            $this->info('No overdue exceptions found.');
            return 0;
        }

        $this->info("Found {$overdueExceptions->count()} overdue exception(s):");

        foreach ($overdueExceptions as $exception) {
            if (!$exception->sla_breached) {
                $exception->update(['sla_breached' => true]);
                $this->warn("- Exception {$exception->exception_id} has breached SLA");
                
                // Send escalation notification
                // Notification::send($admins, new SLABreachedNotification($exception));
            }
        }

        return 0;
    }
}
