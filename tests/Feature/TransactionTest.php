<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Transaction;
use App\Models\User;
use App\Models\MasterAccount;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    protected TransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TransactionService::class);
    }

    public function test_contribution_deducts_from_bank_and_adds_to_fund()
    {
        $user = User::factory()->create();
        $user->member->update([
            'bank_account_balance' => 1000,
            'fund_account_balance' => 0,
        ]);

        $transaction = $this->service->createContribution($user, 100);

        $this->assertEquals('complete', $transaction->status);
        $this->assertEquals(900, $user->fresh()->bank_account_balance);
        $this->assertEquals(100, $user->fresh()->fund_account_balance);
    }

    public function test_contribution_fails_with_insufficient_balance()
    {
        $user = User::factory()->create();
        $user->member->update([
            'bank_account_balance' => 50,
            'fund_account_balance' => 0,
        ]);

        $this->expectException(\Exception::class);
        $this->service->createContribution($user, 100);
    }

    public function test_distribution_transfers_from_master_to_user()
    {
        MasterAccount::where('account_type', 'master_bank')->first()->update(['balance' => 1000]);

        $user = User::factory()->create();
        $user->member->update([
            'bank_account_balance' => 0,
        ]);

        $transaction = $this->service->createDistribution($user, 200);

        $this->assertEquals('complete', $transaction->status);
        $this->assertEquals(200, $user->fresh()->bank_account_balance);
        $this->assertEquals(800, MasterAccount::where('account_type', 'master_bank')->first()->balance);
    }
}
