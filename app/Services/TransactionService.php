<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Models\MasterAccount;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    /**
     * Create and process a contribution transaction
     */
    public function createContribution(User $user, float $amount, ?string $notes = null): Transaction
    {
        if (!$user->hasSufficientBankBalance($amount)) {
            throw new \Exception("Insufficient bank account balance");
        }

        DB::beginTransaction();
        try {
            $transaction = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('CTB'),
                'transaction_date' => now(),
                'type' => 'contribution',
                'from_account' => "User Bank Account - {$user->user_code}",
                'to_account' => "User Fund Account - {$user->user_code}",
                'amount' => $amount,
                'user_id' => $user->id,
                'status' => 'pending',
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            $transaction->process();

            DB::commit();
            return $transaction->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Create distribution from Master Bank to User Banks
     */
    public function createDistribution(User $user, float $amount, ?string $notes = null): Transaction
    {
        $masterBank = MasterAccount::where('account_type', 'master_bank')->first();

        if ($masterBank->balance < $amount) {
            throw new \Exception("Insufficient master bank balance");
        }

        DB::beginTransaction();
        try {
            $transaction = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('DST'),
                'transaction_date' => now(),
                'type' => 'master_to_user_bank',
                'from_account' => 'Master Bank Account',
                'to_account' => "User Bank Account - {$user->user_code}",
                'amount' => $amount,
                'user_id' => $user->id,
                'status' => 'pending',
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            $transaction->process();

            DB::commit();
            return $transaction->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Bulk distribution to multiple users
     */
    public function bulkDistribute(array $distributions): array
    {
        $results = [];
        $masterBank = MasterAccount::where('account_type', 'master_bank')->first();
        $totalAmount = array_sum(array_column($distributions, 'amount'));

        if ($masterBank->balance < $totalAmount) {
            throw new \Exception("Insufficient master bank balance for bulk distribution");
        }

        DB::beginTransaction();
        try {
            foreach ($distributions as $dist) {
                $user = User::find($dist['user_id']);
                $transaction = $this->createDistribution(
                    $user,
                    $dist['amount'],
                    $dist['notes'] ?? "Bulk distribution"
                );
                $results[] = $transaction;
            }

            DB::commit();
            return $results;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get transaction statistics
     */
    public function getStatistics(?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = Transaction::complete();

        if ($startDate) {
            $query->where('transaction_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('transaction_date', '<=', $endDate);
        }

        return [
            'total_transactions' => $query->count(),
            'total_amount' => $query->sum('amount'),
            'by_type' => $query->selectRaw('type, COUNT(*) as count, SUM(amount) as total')
                ->groupBy('type')
                ->get()
                ->keyBy('type')
                ->toArray(),
            'by_status' => $query->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get()
                ->keyBy('status')
                ->toArray(),
        ];
    }
}
