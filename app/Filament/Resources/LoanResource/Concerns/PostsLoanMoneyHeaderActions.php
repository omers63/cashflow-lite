<?php

namespace App\Filament\Resources\LoanResource\Concerns;

use App\Models\Member;
use App\Services\LoanService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;

trait PostsLoanMoneyHeaderActions
{
    /** @return array<int, Action> */
    protected function postsLoanMoneyHeaderActions(): array
    {
        return [
            Action::make('post_repayment')
                ->label('Post repayment')
                ->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn(): bool => $this->record->status === 'active'
                    && (float) $this->record->outstanding_balance > 0.001)
                ->schema([
                    Forms\Components\TextInput::make('amount')
                        ->label('Amount')
                        ->numeric()
                        ->required()
                        ->prefix('$')
                        ->minValue(0.01)
                        ->default(fn(): float => max(0.01, round(min(
                            (float) ($this->record->installment_amount ?? $this->record->monthly_payment ?: 0),
                            (float) $this->record->outstanding_balance
                        ), 2))),
                    Forms\Components\DatePicker::make('repayment_date')
                        ->label('Payment date')
                        ->default(now())
                        ->native(false),
                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $loan = $this->record->fresh();
                    try {
                        app(LoanService::class)->processPayment(
                            $loan,
                            (float) $data['amount'],
                            $data['notes'] ?? null,
                            $data['repayment_date'] ?? null
                        );
                        Notification::make()->title('Repayment posted')->success()->send();
                        $this->refreshAfterLoanMoneyAction();
                    } catch (\Exception $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('post_disbursement')
                ->label('Post disbursement')
                ->icon('heroicon-o-banknotes')
                ->visible(fn(): bool => $this->record->status === 'pending')
                ->requiresConfirmation()
                ->modalHeading(fn(): string => 'Approve and disburse ' . $this->record->loan_id . '?')
                ->modalDescription(function (): string {
                    $record = $this->record;
                    $tier = Member::loanTierFor((float) $record->original_amount);
                    $desc = 'Amount: $' . number_format((float) $record->original_amount, 2);
                    if ($tier) {
                        $pct = (float) ($tier['maturity_percentage'] ?? 16);
                        $desc .= "\nInstallment: $" . number_format($tier['installment_amount'], 2)
                            . "\nMaturity target ({$pct}%): $" . number_format($tier['maturity_balance'], 2);
                    }

                    return $desc;
                })
                ->action(function (): void {
                    $loan = $this->record->fresh();
                    try {
                        $member = $loan->member;
                        if ($member) {
                            $error = $member->checkTierAllocation((float) $loan->original_amount);
                            if ($error) {
                                throw new \Exception($error);
                            }
                        }
                        app(LoanService::class)->approveLoan($loan);
                        Notification::make()->title('Loan approved and disbursed')->success()->send();
                        $this->refreshAfterLoanMoneyAction();
                    } catch (\Exception $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('post_additional_disbursement')
                ->label('Additional disbursement')
                ->icon('heroicon-o-plus-circle')
                ->visible(fn(): bool => $this->record->status === 'active')
                ->schema([
                    Forms\Components\TextInput::make('amount')
                        ->label('Amount')
                        ->numeric()
                        ->required()
                        ->prefix('$')
                        ->minValue(0.01),
                    Forms\Components\DatePicker::make('disbursement_date')
                        ->label('Disbursement date')
                        ->required()
                        ->default(now())
                        ->native(false),
                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $loan = $this->record->fresh();
                    try {
                        app(LoanService::class)->postAdditionalDisbursement(
                            $loan,
                            (float) $data['amount'],
                            $data['disbursement_date'],
                            $data['notes'] ?? null
                        );
                        Notification::make()->title('Disbursement posted')->success()->send();
                        $this->refreshAfterLoanMoneyAction();
                    } catch (\Exception $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    abstract protected function refreshAfterLoanMoneyAction(): void;
}
