<?php

namespace App\Filament\Member\Pages;

use App\Models\Loan;
use App\Models\Member;
use App\Services\LoanService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class Loans extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Loans';
    protected static string|\UnitEnum|null $navigationGroup = 'Loans';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.member.pages.loans';

    public function getTitle(): string|Htmlable
    {
        return 'Loans';
    }

    public function getMember(): ?Member
    {
        return auth()->user()?->member;
    }

    /**
     * Active loans for the member.
     *
     * @return \Illuminate\Support\Collection<int, Loan>
     */
    public function getActiveLoans(): Collection
    {
        $member = $this->getMember();
        if (!$member) {
            return collect();
        }

        return $member->loansQuery()
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Member-side loan actions: request loan, make repayment.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('request_loan')
                ->label('Request loan')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->form(function () {
                    $member = $this->getMember();
                    if (!$member) {
                        return [];
                    }
                    $maxLoan = $member->maxLoanAmount();
                    $errors = $member->loanEligibilityErrors();

                    $fields = [];
                    if (!empty($errors)) {
                        $fields[] = \Filament\Forms\Components\Placeholder::make('_eligibility_warning')
                            ->label('Not eligible')
                            ->content(implode("\n", $errors))
                            ->extraAttributes(['class' => 'text-danger-600 dark:text-danger-400']);
                    }

                    $fields[] = \Filament\Forms\Components\Placeholder::make('_info')
                        ->label('Loan limits')
                        ->content('Max loan: $' . number_format($maxLoan, 2)
                            . ' (2× fund balance $' . number_format((float) $member->fund_account_balance, 2) . ')');

                    $fields[] = \Filament\Forms\Components\TextInput::make('amount')
                        ->label('Loan amount')
                        ->numeric()
                        ->required()
                        ->prefix('$')
                        ->minValue(1000)
                        ->maxValue(min($maxLoan, 300000))
                        ->step(1);

                    $fields[] = \Filament\Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->rows(2);

                    return $fields;
                })
                ->action(function (array $data): void {
                    $user = auth()->user();
                    $member = $user?->member;
                    if (!$member) {
                        Notification::make()->title('No member profile')->danger()->send();
                        return;
                    }
                    $errors = $member->loanEligibilityErrors();
                    if (!empty($errors)) {
                        Notification::make()
                            ->title('Not eligible for a loan')
                            ->body(implode("\n", $errors))
                            ->danger()
                            ->send();
                        return;
                    }
                    $amount = (float) ($data['amount'] ?? 0);
                    if ($amount <= 0) {
                        Notification::make()->title('Invalid amount')->danger()->send();
                        return;
                    }
                    if ($amount > $member->maxLoanAmount()) {
                        Notification::make()
                            ->title('Amount exceeds maximum ($' . number_format($member->maxLoanAmount(), 2) . ')')
                            ->danger()
                            ->send();
                        return;
                    }
                    try {
                        $service = app(LoanService::class);
                        $loan = $service->createLoan($user, $amount, 0.0, 12, $data['notes'] ?? null);
                        Notification::make()
                            ->title('Loan request submitted')
                            ->body("Loan {$loan->loan_id} has been created and is pending approval.")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Loan request failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('make_repayment')
                ->label('Make loan repayment')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->form(function () {
                    $member = $this->getMember();
                    if (!$member) {
                        return [];
                    }
                    $activeLoans = $member->loansQuery()->where('status', 'active')->get();

                    if ($activeLoans->isEmpty()) {
                        return [
                            \Filament\Forms\Components\Placeholder::make('_no_loans')
                                ->label('No active loans')
                                ->content('You do not have any active loans to repay.'),
                        ];
                    }

                    return [
                        \Filament\Forms\Components\Select::make('loan_id')
                            ->label('Loan')
                            ->options($activeLoans->mapWithKeys(fn(Loan $loan) => [
                                $loan->id => "{$loan->loan_id} – $" . number_format((float) $loan->outstanding_balance, 2),
                            ]))
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('amount')
                            ->label('Repayment amount')
                            ->numeric()
                            ->required()
                            ->prefix('$')
                            ->minValue(1),
                    ];
                })
                ->action(function (array $data): void {
                    $member = $this->getMember();
                    if (!$member) {
                        Notification::make()->title('No member profile')->danger()->send();
                        return;
                    }
                    $loanId = (int) ($data['loan_id'] ?? 0);
                    $amount = (float) ($data['amount'] ?? 0);
                    $loan = Loan::find($loanId);
                    if (!$loan || $loan->member_id !== $member->id) {
                        Notification::make()->title('Invalid loan')->danger()->send();
                        return;
                    }
                    if ($amount <= 0) {
                        Notification::make()->title('Invalid amount')->danger()->send();
                        return;
                    }
                    try {
                        $service = app(LoanService::class);
                        $service->processPayment($loan, $amount);
                        Notification::make()
                            ->title('Repayment posted')
                            ->body("Loan {$loan->loan_id}: repayment of $" . number_format($amount, 2) . ' recorded.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Repayment failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}

