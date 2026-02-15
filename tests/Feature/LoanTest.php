<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Loan;
use App\Models\User;
use App\Models\MasterAccount;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoanTest extends TestCase
{
    use RefreshDatabase;

    protected LoanService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LoanService::class);
    }

    public function test_loan_creation_with_valid_parameters()
    {
        $user = User::factory()->create([
            'fund_account_balance' => 5000,
            'outstanding_loans' => 0,
        ]);

        $loan = $this->service->createLoan($user, 1000, 10, 12);

        $this->assertEquals('pending', $loan->status);
        $this->assertEquals(1000, $loan->original_amount);
        $this->assertEquals(1000, $loan->outstanding_balance);
    }

    public function test_loan_approval_disburses_funds()
    {
        MasterAccount::where('account_type', 'master_fund')->first()->update(['balance' => 5000]);

        $user = User::factory()->create([
            'fund_account_balance' => 5000,
            'outstanding_loans' => 0,
            'bank_account_balance' => 0,
        ]);

        $loan = $this->service->createLoan($user, 1000, 10, 12);
        $transaction = $this->service->approveLoan($loan);

        $this->assertEquals('active', $loan->fresh()->status);
        $this->assertEquals('complete', $transaction->status);
        $this->assertEquals(1000, $user->fresh()->bank_account_balance);
    }

    public function test_loan_payment_reduces_balance()
    {
        $user = User::factory()->create([
            'bank_account_balance' => 500,
            'fund_account_balance' => 5000,
            'outstanding_loans' => 1000,
        ]);

        $loan = Loan::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'outstanding_balance' => 1000,
            'monthly_payment' => 100,
        ]);

        $transaction = $this->service->processPayment($loan, 100);

        $this->assertEquals('complete', $transaction->status);
        $this->assertLessThan(1000, $loan->fresh()->outstanding_balance);
    }
}
