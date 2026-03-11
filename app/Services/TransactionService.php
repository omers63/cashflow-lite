<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Models\MasterAccount;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    /**
     * Create and process a contribution transaction (Paired: Debit User Bank, Credit Master Fund)
     */
    public function createContribution(User $user, float $amount, ?string $notes = null): Transaction
    {
        if (!$user->hasSufficientBankBalance($amount)) {
            throw new \Exception("Insufficient bank account balance");
        }

        return DB::transaction(function () use ($user, $amount, $notes) {
            // 1. Debit User Bank
            $debit = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('CTB-D'),
                'transaction_date' => now(),
                'type' => 'debit',
                'target_account' => 'user_bank',
                'amount' => $amount,
                'user_id' => $user->id,
                'status' => 'pending',
                'notes' => $notes ? "DEBIT: {$notes}" : "Contribution Debit from Bank",
                'created_by' => auth()->id(),
            ]);
            $debit->process();

            // 2. Credit Master Fund (This also credits user's fund share via Model logic)
            $credit = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('CTB-C'),
                'transaction_date' => now(),
                'type' => 'contribution', // Keep type for reporting, logic handles it as credit
                'target_account' => 'master_fund',
                'amount' => $amount,
                'user_id' => $user->id,
                'related_transaction_id' => $debit->id,
                'status' => 'pending',
                'notes' => $notes ? "CREDIT: {$notes}" : "Contribution Credit to Fund",
                'created_by' => auth()->id(),
            ]);
            $credit->process();

            return $credit->fresh();
        });
    }

    /**
     * Create distribution from Master Bank to User Banks (Paired: Debit Master Bank, Credit User Bank)
     */
    public function createDistribution(User $user, float $amount, ?string $notes = null): Transaction
    {
        $masterBank = MasterAccount::where('account_type', 'master_bank')->first();

        if ($masterBank->balance < $amount) {
            throw new \Exception("Insufficient master bank balance");
        }

        return DB::transaction(function () use ($user, $amount, $notes) {
            // 1. Debit Master Bank
            $debit = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('DST-D'),
                'transaction_date' => now(),
                'type' => 'debit',
                'target_account' => 'master_bank',
                'amount' => $amount,
                'status' => 'pending',
                'notes' => $notes ? "DEBIT: {$notes}" : "Distribution Debit from Master Bank",
                'created_by' => auth()->id(),
            ]);
            $debit->process();

            // 2. Credit User Bank
            $credit = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('DST-C'),
                'transaction_date' => now(),
                'type' => 'master_to_user_bank',
                'target_account' => 'user_bank',
                'amount' => $amount,
                'user_id' => $user->id,
                'related_transaction_id' => $debit->id,
                'status' => 'pending',
                'notes' => $notes ? "CREDIT: {$notes}" : "Distribution Credit to User Bank",
                'created_by' => auth()->id(),
            ]);
            $credit->process();

            return $credit->fresh();
        });
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
