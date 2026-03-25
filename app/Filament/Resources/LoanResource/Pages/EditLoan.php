<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Filament\Resources\LoanResource;
use App\Filament\Resources\LoanResource\Concerns\PostsLoanMoneyHeaderActions;
use App\Models\Member;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditLoan extends EditRecord
{
    use PostsLoanMoneyHeaderActions;

    protected static string $resource = LoanResource::class;

    protected function refreshAfterLoanMoneyAction(): void
    {
        $this->redirect(LoanResource::getUrl('edit', ['record' => $this->record]));
    }

    protected function getHeaderActions(): array
    {
        return array_merge(
            $this->postsLoanMoneyHeaderActions(),
            [
            Actions\Action::make('reset_payment_history')
                ->label('Reset payments')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reset all payments for this loan?')
                ->modalDescription('This deletes every loan payment record and recalculates outstanding balance, total paid, next payment date, and status from the remaining (none) history. Ledger transactions are not removed — delete or adjust those in Transactions if your books must stay aligned.')
                ->visible(fn (): bool => $this->record->payments()->exists())
                ->action(function (): void {
                    $this->record->resetPaymentHistory();
                    $this->record->refresh();
                    Notification::make()
                        ->title('Payment history reset')
                        ->success()
                        ->send();
                    $this->redirect(LoanResource::getUrl('edit', ['record' => $this->record]));
                }),

            Actions\Action::make('clear_disbursement_schedule')
                ->label('Clear disbursement schedule')
                ->icon('heroicon-o-calendar-days')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Clear planned disbursement schedule?')
                ->modalDescription('This removes all planned disbursement rows (the schedule). Fully disbursed date, first repayment date, and maturity are recalculated from the origination date. Posted disbursement transactions are not reversed.')
                ->visible(fn (): bool => $this->record->disbursements()->exists())
                ->action(function (): void {
                    $this->record->clearPlannedDisbursements();
                    $this->record->refresh();
                    Notification::make()
                        ->title('Disbursement schedule cleared')
                        ->success()
                        ->send();
                    $this->redirect(LoanResource::getUrl('edit', ['record' => $this->record]));
                }),

            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            ]
        );
    }

    protected function getRedirectUrl(): string
    {
        return LoanResource::getUrl('index');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $loan = $this->record;
        if ($loan->member_id) {
            $member = Member::find($loan->member_id);
            if ($member) {
                $data['_fund_balance'] = (float) $member->fund_account_balance;
                $data['_max_loan'] = $member->maxLoanAmount();
                $data['_eligibility_errors'] = implode("\n", $member->loanEligibilityErrors());
            }
        }

        $data['disbursement_schedule'] = $loan->disbursements()
            ->orderBy('disbursement_date')
            ->get()
            ->map(fn ($d) => [
                'disbursement_date' => $d->disbursement_date?->format('Y-m-d'),
                'amount' => (float) $d->amount,
            ])
            ->values()
            ->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset(
            $data['_fund_balance'],
            $data['_max_loan'],
            $data['_eligibility_errors'],
            $data['force_create_old_loan'],
            $data['disbursement_schedule'],
            $data['override_allocation_limit'],
        );

        return $data;
    }
}
