<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Models\LoanDisbursement;
use App\Models\Member;
use App\Filament\Resources\LoanResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateLoan extends CreateRecord
{
    protected static string $resource = LoanResource::class;

    /** @var array<int, array{disbursement_date: string, amount: string|float}> */
    protected array $pendingDisbursementSchedule = [];

    protected function getCreateFormAction(): Actions\Action
    {
        return parent::getCreateFormAction()
            ->submit(null)
            ->action(fn () => $this->create())
            ->requiresConfirmation()
            ->modalHeading('Confirm loan creation')
            ->modalDescription(function () {
                $data = $this->form->getState();
                $memberId = (int) ($data['member_id'] ?? 0);
                $member = $memberId ? Member::with('user')->find($memberId) : null;
                $memberName = $member && $member->user ? $member->user->name : '—';
                $amount = (float) ($data['original_amount'] ?? 0);
                $tier = $amount > 0 ? Member::loanTierFor($amount) : null;
                $schedule = $data['disbursement_schedule'] ?? [];
                $scheduleLines = is_array($schedule) && count($schedule) > 0
                    ? array_map(fn ($row) => (\Carbon\Carbon::parse($row['disbursement_date'] ?? '')->format('M j, Y') . ': $' . number_format((float) ($row['amount'] ?? 0), 2)), $schedule)
                    : ['Single disbursement at approval'];

                $lines = [
                    "Member: {$memberName}",
                    'Amount: $' . number_format($amount, 2),
                    $tier ? 'Installment: $' . number_format($tier['installment_amount'], 2) . ' / month' : '',
                    'Disbursement: ' . implode('; ', $scheduleLines),
                ];

                return implode("\n", array_filter($lines));
            })
            ->modalSubmitActionLabel('Create loan');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $amount = round((float) ($data['original_amount'] ?? 0), 0);
        $data['original_amount'] = $amount;
        $tier = \App\Models\Member::loanTierFor($amount);

        $data['loan_id'] = \App\Models\Loan::generateLoanId();

        $term = (int) ($data['term_months'] ?? 12);

        if ($tier) {
            $data['installment_amount'] = $tier['installment_amount'];
            $data['maturity_fund_balance'] = $tier['maturity_balance'];
        } else {
            $data['installment_amount'] = null;
            $data['maturity_fund_balance'] = null;
        }

        $rate = (float) ($data['interest_rate'] ?? 0);
        $data['monthly_payment'] = \App\Models\Loan::calculateMonthlyPayment($amount, $rate, $term);

        $data['total_paid'] = 0;
        $data['outstanding_balance'] = $amount;

        if (! empty($data['disbursement_schedule']) && is_array($data['disbursement_schedule'])) {
            $this->pendingDisbursementSchedule = $data['disbursement_schedule'];
        } elseif (! empty($data['force_create_old_loan']) && ! empty($data['origination_date'])) {
            // Single backdated disbursement: use origination date and full amount
            $this->pendingDisbursementSchedule = [
                [
                    'disbursement_date' => $data['origination_date'],
                    'amount' => (float) $data['original_amount'],
                ],
            ];
        }
        unset(
            $data['_fund_balance'],
            $data['_max_loan'],
            $data['_eligibility_errors'],
            $data['force_create_old_loan'],
            $data['disbursement_schedule'],
            $data['override_allocation_limit']
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        $loan = $this->record;

        // Keep member_id and user_id aligned (Hidden user_id can be missing if state never synced).
        if ($loan->member_id && ! $loan->user_id) {
            $loan->user_id = Member::query()->whereKey($loan->member_id)->value('user_id');
        }
        if ($loan->user_id && ! $loan->member_id) {
            $loan->member_id = Member::query()->where('user_id', $loan->user_id)->value('id');
        }
        if ($loan->isDirty()) {
            $loan->save();
        }

        foreach ($this->pendingDisbursementSchedule as $row) {
            $date = $row['disbursement_date'] ?? null;
            $amount = (float) ($row['amount'] ?? 0);
            if ($date && $amount > 0) {
                LoanDisbursement::create([
                    'loan_id' => $loan->id,
                    'disbursement_date' => $date,
                    'amount' => $amount,
                ]);
            }
        }
        if (count($this->pendingDisbursementSchedule) > 0) {
            $loan->refresh();
            $loan->updateRepaymentAndMaturityDatesFromDisbursements();
        }
        $this->pendingDisbursementSchedule = [];
    }
}
