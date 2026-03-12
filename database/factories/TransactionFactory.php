<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $types = ['external_import', 'master_to_user_bank', 'contribution', 'loan_repayment'];
        $type = fake()->randomElement($types);

        return [
            'transaction_id' => Transaction::generateTransactionId(),
            'transaction_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'type' => $type,
            'debit_or_credit' => $this->getDebitOrCredit($type),
            'target_account' => $this->getTargetAccount($type),
            'amount' => fake()->randomFloat(2, 10, 1000),
            'user_id' => User::factory(),
            'reference' => fake()->optional()->uuid(),
            'status' => fake()->randomElement(['pending', 'complete']),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    private function getDebitOrCredit(string $type): string
    {
        return match ($type) {
            'external_import', 'contribution', 'loan_repayment', 'master_to_user_bank' => 'credit',
            default => 'debit',
        };
    }

    private function getTargetAccount(string $type): string
    {
        return match ($type) {
            'external_import' => 'master_bank',
            'master_to_user_bank' => 'user_bank',
            'contribution' => 'master_fund',
            'loan_repayment' => 'master_fund',
            default => 'user_bank',
        };
    }
}
