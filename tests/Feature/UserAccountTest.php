<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_to_borrow_calculation()
    {
        $user = User::factory()->create([
            'fund_account_balance' => 5000,
            'outstanding_loans' => 2000,
        ]);

        $this->assertEquals(3000, $user->available_to_borrow);
    }

    public function test_available_to_borrow_never_negative()
    {
        $user = User::factory()->create([
            'fund_account_balance' => 1000,
            'outstanding_loans' => 2000,
        ]);

        $this->assertEquals(0, $user->available_to_borrow);
    }

    public function test_can_borrow_check()
    {
        $user = User::factory()->create([
            'fund_account_balance' => 5000,
            'outstanding_loans' => 0,
        ]);

        $this->assertTrue($user->canBorrow(3000));
        $this->assertFalse($user->canBorrow(6000));
    }
}
