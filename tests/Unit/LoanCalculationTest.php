<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Loan;

class LoanCalculationTest extends TestCase
{
    public function test_monthly_payment_calculation()
    {
        $payment = Loan::calculateMonthlyPayment(10000, 10, 12);
        
        // Expected monthly payment for $10,000 at 10% for 12 months is approximately $879.16
        $this->assertEqualsWithDelta(879.16, $payment, 0.50);
    }

    public function test_zero_interest_rate_calculation()
    {
        $payment = Loan::calculateMonthlyPayment(12000, 0, 12);
        
        // With 0% interest, payment should be exactly principal/term
        $this->assertEquals(1000, $payment);
    }

    public function test_interest_calculation()
    {
        $loan = new Loan([
            'outstanding_balance' => 10000,
            'interest_rate' => 12, // 12% annual
        ]);

        $interest = $loan->calculateInterest();
        
        // Monthly interest should be 1% of balance = $100
        $this->assertEquals(100, $interest);
    }
}
