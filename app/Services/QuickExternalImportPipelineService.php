<?php

namespace App\Services;

use App\Models\ExternalBankImport;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Runs: external import row → post to master bank → (credit) post to member bank + contribution/repayment,
 * or (debit) loan disbursement from master fund. Negative amounts are stored on the import; debit direction
 * uses a positive amount in the UI and is stored as negative on the import row.
 */
class QuickExternalImportPipelineService
{
    public function __construct(
        protected TransactionService $transactionService,
        protected LoanService $loanService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{import: ExternalBankImport, master_transaction: Transaction, member_bank_credit: Transaction, fund_transaction: Transaction}
     */
    public function run(array $data): array
    {
        $direction = $data['entry_direction'] ?? 'credit';
        $raw = round(abs((float) ($data['amount'] ?? 0)), 2);
        if ($raw < 0.01) {
            throw ValidationException::withMessages([
                'data.amount' => ['Enter an amount of at least $0.01.'],
            ]);
        }

        $signedAmount = $direction === 'debit' ? -$raw : $raw;

        $member = Member::query()->findOrFail((int) $data['member_id']);
        $destination = $data['destination'] ?? 'contribution';

        return DB::transaction(function () use ($data, $signedAmount, $raw, $member, $destination) {
            $import = ExternalBankImport::create([
                'external_bank_account_id' => (int) $data['external_bank_account_id'],
                'import_date' => now()->toDateString(),
                'transaction_date' => $data['transaction_date'],
                'external_ref_id' => $data['external_ref_id'],
                'amount' => $signedAmount,
                'description' => $data['description'] ?? null,
                'notes' => $data['import_notes'] ?? null,
                'imported_by' => auth()->id(),
                'is_duplicate' => false,
            ]);

            $import->postToMasterBank();
            $import->refresh();
            $masterTransaction = $import->transaction;
            if (! $masterTransaction) {
                throw new \RuntimeException('Master bank transaction was not created.');
            }

            if ($signedAmount < 0) {
                return $this->runDebitDisbursementPath($data, $import, $masterTransaction, $member, $raw);
            }

            $memberBankCredit = $this->transactionService->postExternalImportToMember($masterTransaction, $member);
            $member->refresh();

            if ($destination === 'contribution') {
                $fundTransaction = $this->runContributionPath($data, $member, $raw);
            } else {
                $fundTransaction = $this->runLoanRepaymentPath($data, $member);
            }

            return [
                'import' => $import->fresh(),
                'master_transaction' => $masterTransaction->fresh(),
                'member_bank_credit' => $memberBankCredit->fresh(),
                'fund_transaction' => $fundTransaction->fresh(),
            ];
        });
    }

    /**
     * Negative import: skip posting the master import as a member bank credit; disburse from master fund like a loan payout.
     *
     * @return array{import: ExternalBankImport, master_transaction: Transaction, member_bank_credit: Transaction, fund_transaction: Transaction}
     */
    protected function runDebitDisbursementPath(
        array $data,
        ExternalBankImport $import,
        Transaction $masterTransaction,
        Member $member,
        float $absoluteAmount,
    ): array {
        $loan = Loan::query()
            ->whereKey((int) ($data['loan_id'] ?? 0))
            ->where('member_id', $member->id)
            ->where('status', 'active')
            ->firstOrFail();

        $disbursementDate = $data['repayment_date'] ?? null;
        $date = $disbursementDate ? Carbon::parse($disbursementDate) : now();

        $fundTransaction = $this->loanService->postAdditionalDisbursement(
            $loan,
            $absoluteAmount,
            $date,
            $data['fund_notes'] ?? null,
        );

        $masterTransaction->update(['user_id' => $member->user_id]);

        return [
            'import' => $import->fresh(),
            'master_transaction' => $masterTransaction->fresh(),
            'member_bank_credit' => $fundTransaction->fresh(),
            'fund_transaction' => $fundTransaction->fresh(),
        ];
    }

    /**
     * @return \App\Models\Transaction
     */
    protected function runContributionPath(array $data, Member $member, float $importAmount)
    {
        $contributionAmount = isset($data['contribution_amount']) && $data['contribution_amount'] !== ''
            ? round((float) $data['contribution_amount'], 2)
            : $importAmount;

        if ($contributionAmount <= 0) {
            throw ValidationException::withMessages([
                'data.contribution_amount' => ['Contribution amount must be positive.'],
            ]);
        }

        if ($contributionAmount > (float) $member->bank_account_balance + 0.005) {
            throw ValidationException::withMessages([
                'data.contribution_amount' => [
                    'Contribution cannot exceed member bank balance ($'.number_format((float) $member->bank_account_balance, 2).').',
                ],
            ]);
        }

        $collectionClassification = $this->buildCollectionClassification($data);

        return $this->transactionService->createContribution(
            $member->user,
            $contributionAmount,
            $data['fund_notes'] ?? null,
            $collectionClassification,
            $data['transaction_date'] ?? null,
        );
    }

    /**
     * Custom repayment amount: processed after the import credit; any leftover import funds remain in the member bank.
     *
     * @return \App\Models\Transaction
     */
    protected function runLoanRepaymentPath(array $data, Member $member)
    {
        $loan = Loan::query()
            ->whereKey((int) ($data['loan_id'] ?? 0))
            ->where('member_id', $member->id)
            ->where('status', 'active')
            ->firstOrFail();

        $repaymentAmount = round((float) ($data['repayment_amount'] ?? 0), 2);
        if ($repaymentAmount <= 0) {
            throw ValidationException::withMessages([
                'data.repayment_amount' => ['Enter a repayment amount greater than zero.'],
            ]);
        }

        if ((float) $member->bank_account_balance + 0.005 < $repaymentAmount) {
            throw ValidationException::withMessages([
                'data.repayment_amount' => [
                    'Member bank balance ($'.number_format((float) $member->bank_account_balance, 2).
                    ') is insufficient for this repayment ($'.number_format($repaymentAmount, 2).'). The import amount stays in the member bank when you do not run this pipeline.',
                ],
            ]);
        }

        return $this->loanService->processPayment(
            $loan,
            $repaymentAmount,
            $data['fund_notes'] ?? null,
            $data['repayment_date'] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{obligation_month?: \DateTimeInterface|string|null, period_due_date?: \DateTimeInterface|string|null, is_late?: bool|null}|null
     */
    protected function buildCollectionClassification(array $data): ?array
    {
        if (empty($data['collection_obligation_month'])) {
            return null;
        }

        $classification = [
            'obligation_month' => $data['collection_obligation_month'],
            'period_due_date' => $data['collection_period_due_date'] ?? null,
        ];

        $timing = $data['collection_timing_override'] ?? 'auto';
        if ($timing === 'on_time') {
            $classification['is_late'] = false;
        } elseif ($timing === 'late') {
            $classification['is_late'] = true;
        }

        return $classification;
    }
}
