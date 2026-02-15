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
        // Setup balanced system
        MasterAccount::where('account_type', 'master_bank')->first()->update(['balance' => 1000]);
        MasterAccount::where('account_type', 'master_fund')->first()->update(['balance' => 500]);
        
        User::factory()->create([
            'bank_account_balance' => 500,
            'fund_account_balance' => 500,
            'outstanding_loans' => 0,
        ]);

        $reconciliation = $this->service->runDailyReconciliation();

        $this->assertTrue($reconciliation->all_passed);
        $this->assertEquals(7, $reconciliation->checks_passed);
        $this->assertEquals(0, $reconciliation->checks_failed);
    }

    public function test_reconciliation_fails_with_imbalanced_accounts()
    {
        // Setup imbalanced system
        MasterAccount::where('account_type', 'master_bank')->first()->update(['balance' => 1000]);
        MasterAccount::where('account_type', 'master_fund')->first()->update(['balance' => 100]);
        
        User::factory()->create([
            'bank_account_balance' => 500,
            'fund_account_balance' => 500,
            'outstanding_loans' => 0,
        ]);

        $reconciliation = $this->service->runDailyReconciliation();

        $this->assertFalse($reconciliation->all_passed);
        $this->assertGreaterThan(0, $reconciliation->checks_failed);
    }
}
