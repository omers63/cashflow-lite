<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\ReconciliationService;
use App\Models\MasterAccount;
use App\Models\User;
use App\Models\Loan;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected ReconciliationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReconciliationService::class);
    }

    public function test_daily_reconciliation_passes_with_balanced_accounts()
    {
        // No transactions yet: stored balances must match recomputed (0) and cross-checks (E2, M2).
        MasterAccount::where('account_type', 'master_bank')->first()->update(['balance' => 0]);
        MasterAccount::where('account_type', 'master_fund')->first()->update(['balance' => 0]);

        $user = User::factory()->create();
        $user->member->update([
            'bank_account_balance' => 0,
            'fund_account_balance' => 0,
            'outstanding_loans' => 0,
        ]);

        $reconciliation = $this->service->runDailyReconciliation();

        $this->assertTrue($reconciliation->all_passed);
        $this->assertSame(0, $reconciliation->checks_failed);
        $this->assertGreaterThan(0, $reconciliation->checks_passed);
    }

    public function test_reconciliation_fails_with_imbalanced_accounts()
    {
        // Setup imbalanced system
        MasterAccount::where('account_type', 'master_bank')->first()->update(['balance' => 1000]);
        MasterAccount::where('account_type', 'master_fund')->first()->update(['balance' => 100]);

        $user = User::factory()->create();
        $user->member->update([
            'bank_account_balance' => 500,
            'fund_account_balance' => 500,
            'outstanding_loans' => 0,
        ]);

        $reconciliation = $this->service->runDailyReconciliation();

        $this->assertFalse($reconciliation->all_passed);
        $this->assertGreaterThan(0, $reconciliation->checks_failed);
    }
}
