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
            'from_account' => $this->getFromAccount($type),
            'to_account' => $this->getToAccount($type),
            'amount' => fake()->randomFloat(2, 10, 1000),
            'user_id' => User::factory(),
            'reference' => fake()->optional()->uuid(),
            'status' => fake()->randomElement(['pending', 'complete']),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    private function getFromAccount($type): string
    {
        return match($type) {
            'external_import' => 'External Bank',
            'master_to_user_bank' => 'Master Bank Account',
            'contribution' => 'User Bank Account',
            'loan_repayment' => 'User Bank Account',
            default => 'Unknown',
        };
    }

    private function getToAccount($type): string
    {
        return match($type) {
            'external_import' => 'Master Bank Account',
            'master_to_user_bank' => 'User Bank Account',
            'contribution' => 'User Fund Account',
            'loan_repayment' => 'User Fund Account',
            default => 'Unknown',
        };
    }
}
