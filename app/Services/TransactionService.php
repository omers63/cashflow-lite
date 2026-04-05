<?php

namespace App\Services;

use App\Models\MasterAccount;
use Carbon\Carbon;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    /**
     * Create and process a contribution transaction (Paired: Debit User Bank, Credit Master Fund)
     *
     * @param  array{obligation_month?: \DateTimeInterface|string|null, period_due_date?: \DateTimeInterface|string|null, is_late?: bool|null}|null  $collectionClassification
     * @param  \DateTimeInterface|Carbon|string|null  $transactionDate  Defaults to now when null.
     */
    public function createContribution(
        User $user,
        float $amount,
        ?string $notes = null,
        ?array $collectionClassification = null,
        \DateTimeInterface|Carbon|string|null $transactionDate = null,
    ): Transaction {
        if (! $user->hasSufficientBankBalance($amount)) {
            throw new \Exception('Insufficient bank account balance');
        }

        $paymentDate = $transactionDate === null ? now() : Carbon::parse($transactionDate);
        $collectionAttrs = app(MonthlyCollectionsService::class)
            ->transactionAttributesForCollectionClassification($collectionClassification, $paymentDate);

        return DB::transaction(function () use ($user, $amount, $notes, $paymentDate, $collectionAttrs) {
            // 1. Debit User Bank
            $debit = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('CTB-D'),
                'transaction_date' => $paymentDate,
                'type' => 'debit',
                'debit_or_credit' => 'debit',
                'target_account' => 'user_bank',
                'amount' => $amount,
                'user_id' => $user->id,
                'status' => 'pending',
                'notes' => $notes ? "DEBIT: {$notes}" : 'Contribution Debit from Bank',
                'created_by' => auth()->id(),
            ]);
            $debit->process();

            // 2. Credit Master Fund (This also credits user's fund share via Model logic)
            $credit = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('CTB-C'),
                'transaction_date' => $paymentDate,
                'type' => 'contribution',
                'debit_or_credit' => 'credit',
                'target_account' => 'master_fund',
                'amount' => $amount,
                'user_id' => $user->id,
                'related_transaction_id' => $debit->id,
                'status' => 'pending',
                'notes' => $notes ? "CREDIT: {$notes}" : 'Contribution Credit to Fund',
                'created_by' => auth()->id(),
                ...$collectionAttrs,
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
            throw new \Exception('Insufficient master bank balance');
        }

        return DB::transaction(function () use ($user, $amount, $notes) {
            // 1. Debit Master Bank
            $debit = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('DST-D'),
                'transaction_date' => now(),
                'type' => 'debit',
                'debit_or_credit' => 'debit',
                'target_account' => 'master_bank',
                'amount' => $amount,
                'status' => 'pending',
                'notes' => $notes ? "DEBIT: {$notes}" : 'Distribution Debit from Master Bank',
                'created_by' => auth()->id(),
            ]);
            $debit->process();

            // 2. Credit User Bank
            $credit = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('DST-C'),
                'transaction_date' => now(),
                'type' => 'master_to_user_bank',
                'debit_or_credit' => 'credit',
                'target_account' => 'user_bank',
                'amount' => $amount,
                'user_id' => $user->id,
                'related_transaction_id' => $debit->id,
                'status' => 'pending',
                'notes' => $notes ? "CREDIT: {$notes}" : 'Distribution Credit to User Bank',
                'created_by' => auth()->id(),
            ]);
            $credit->process();

            return $credit->fresh();
        });
    }

    /**
     * Post a completed master-bank external import to a member: create a user_bank credit
     * transaction (same pattern as distribution credits) and assign the master row to the member.
     */
    public function postExternalImportToMember(Transaction $masterBankImport, Member $member): Transaction
    {
        return DB::transaction(function () use ($masterBankImport, $member) {
            $masterBankImport->refresh();

            if ($masterBankImport->target_account !== 'master_bank' || $masterBankImport->type !== 'external_import') {
                throw new \InvalidArgumentException('Only master bank external import transactions can be posted to a member.');
            }

            if ($masterBankImport->status !== 'complete') {
                throw new \InvalidArgumentException('Only completed transactions can be posted to a member.');
            }

            if ($masterBankImport->user_id) {
                throw new \InvalidArgumentException('This import is already assigned to a member.');
            }

            $credit = Transaction::create([
                'transaction_id' => Transaction::generateTransactionId('IMP-C'),
                'transaction_date' => $masterBankImport->transaction_date ?? now(),
                'type' => 'master_to_user_bank',
                'debit_or_credit' => 'credit',
                'target_account' => 'user_bank',
                'amount' => $masterBankImport->amount,
                'user_id' => $member->user_id,
                'related_transaction_id' => $masterBankImport->id,
                'reference' => $masterBankImport->transaction_id,
                'status' => 'pending',
                'notes' => sprintf('Member bank credit from posted import [%s]', $masterBankImport->transaction_id),
                'created_by' => auth()->id(),
            ]);
            $credit->process();

            $masterBankImport->update(['user_id' => $member->user_id]);

            return $credit->fresh();
        });
    }

    /**
     * Undo the member bank side of a posted external import (before reassign or correction).
     * Prefers reversing the ledger user_bank credit linked via related_transaction_id; otherwise
     * debits the member bank directly (legacy rows posted before ledger credits existed).
     */
    public function undoExternalImportMemberAssignment(Transaction $masterBankImport): void
    {
        DB::transaction(function () use ($masterBankImport) {
            $masterBankImport->refresh();

            $existing = Transaction::query()
                ->where('related_transaction_id', $masterBankImport->id)
                ->where('target_account', 'user_bank')
                ->where('type', 'master_to_user_bank')
                ->where('status', 'complete')
                ->first();

            if ($existing) {
                $existing->reverse('Reassigned or unposted from master import');

                $masterBankImport->update(['user_id' => null]);

                return;
            }

            $oldMember = $masterBankImport->user?->member;
            if ($oldMember && $masterBankImport->user_id) {
                $oldMember->debitBankAccount((float) $masterBankImport->amount);
            }

            $masterBankImport->update(['user_id' => null]);
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
            throw new \Exception('Insufficient master bank balance for bulk distribution');
        }

        DB::beginTransaction();
        try {
            foreach ($distributions as $dist) {
                $user = User::find($dist['user_id']);
                $transaction = $this->createDistribution(
                    $user,
                    $dist['amount'],
                    $dist['notes'] ?? 'Bulk distribution'
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
