<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition(): array
    {
        $originalAmount = fake()->randomFloat(2, 1000, 10000);
        $interestRate = fake()->randomFloat(2, 5, 15);
        $termMonths = fake()->randomElement([12, 24, 36, 48, 60]);
        $monthlyPayment = Loan::calculateMonthlyPayment($originalAmount, $interestRate, $termMonths);

        return [
            'loan_id' => Loan::generateLoanId(),
            'user_id' => User::factory(),
            'origination_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'original_amount' => $originalAmount,
            'interest_rate' => $interestRate,
            'term_months' => $termMonths,
            'monthly_payment' => $monthlyPayment,
            'total_paid' => 0,
            'outstanding_balance' => $originalAmount,
            'status' => 'active',
            'next_payment_date' => now()->addMonth(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
