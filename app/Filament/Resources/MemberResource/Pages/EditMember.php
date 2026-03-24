<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Models\Loan;
use App\Models\Member;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\On;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    #[On('refreshMemberRecord')]
    public function refreshMemberRecord(?int $memberId = null): void
    {
        if ($memberId !== null && $this->record->getKey() !== $memberId) {
            return;
        }
        $fresh = $this->record->fresh();
        if ($fresh) {
            $this->record = $fresh;
        } else {
            $this->record->refresh();
        }
        $this->refreshFormData(['bank_account_balance', 'fund_account_balance', 'outstanding_loans']);
    }

    protected function getHeaderActions(): array
    {
        /** @var Member $member */
        $member = $this->record;

        $dependants = $member->dependants()->with('user')->get();

        return [
            Actions\Action::make('set_allowances')
                ->label('')
                ->tooltip('Set Allowances')
                ->icon('heroicon-o-adjustments-horizontal')
                ->link()
                ->visible(fn() => $member->isParentMember())
                ->form(function () use ($member, $dependants) {
                    $allocationOptions = array_combine(
                        Member::ALLOCATION_OPTIONS,
                        array_map(fn($v) => '$' . number_format($v, 0), Member::ALLOCATION_OPTIONS)
                    );
                    $fields = [
                        Forms\Components\Select::make("allowance_{$member->id}")
                            ->label(($member->user ? "{$member->user->name} ({$member->user->user_code})" : "Member #{$member->id}") . ' (yourself)')
                            ->options($allocationOptions)
                            ->default((int) ($member->allowed_allocation ?? 500))
                            ->required(),
                    ];
                    foreach ($dependants as $d) {
                        $fields[] = Forms\Components\Select::make("allowance_{$d->id}")
                            ->label($d->user ? "{$d->user->name} ({$d->user->user_code})" : "Member #{$d->id}")
                            ->options($allocationOptions)
                            ->default((int) ($d->allowed_allocation ?? 500))
                            ->required();
                    }
                    return $fields;
                })
                ->action(function (array $data) use ($member, $dependants): void {
                    $member->update(['allowed_allocation' => (int) $data["allowance_{$member->id}"]]);
                    foreach ($dependants as $d) {
                        $d->update(['allowed_allocation' => (int) $data["allowance_{$d->id}"]]);
                    }
                    $this->record = $member->fresh();
                    $this->refreshFormData(['allowed_allocation']);
                    Notification::make()
                        ->title('Allowances saved')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('allocate_to_dependant')
                ->label('')
                ->tooltip('Allocate to Dependant')
                ->icon('heroicon-o-banknotes')
                ->link()
                ->visible(fn() => $member->isParentMember())
                ->form([
                    Forms\Components\Select::make('dependent_id')
                        ->label('Dependant')
                        ->options(
                            $dependants->mapWithKeys(
                                fn(Member $d) => [$d->id => $d->user ? "{$d->user->name} ({$d->user->user_code})" : "Member #{$d->id}"]
                            )
                        )
                        ->required()
                        ->live()
                        ->helperText(function ($get) use ($member, $dependants) {
                            $dep = $dependants->find((int) $get('dependent_id'));
                            if (!$dep) {
                                return null;
                            }
                            $amount = (int) ($dep->allowed_allocation ?? 500);
                            $bank = (float) $member->bank_account_balance;
                            $sufficient = $bank >= $amount;
                            return 'Amount to allocate: $' . number_format($amount, 2)
                                . ' — your bank balance: $' . number_format($bank, 2)
                                . ($sufficient ? '' : ' ⚠ Insufficient balance');
                        }),
                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->rows(2),
                    Forms\Components\DatePicker::make('allocation_date')
                        ->label('Allocation date (optional)')
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    $member = $this->record->fresh();
                    $dependent = Member::query()->find((int) $data['dependent_id']);
                    if (!$dependent || $dependent->parent_id !== $member->id) {
                        Notification::make()->title('Invalid dependant')->danger()->send();
                        return;
                    }
                    $amount = (int) ($dependent->allowed_allocation ?? 500);
                    if ((float) $member->bank_account_balance < $amount) {
                        Notification::make()
                            ->title('Insufficient bank balance')
                            ->body('Need $' . number_format($amount, 2) . ', available $' . number_format((float) $member->bank_account_balance, 2) . '.')
                            ->danger()
                            ->send();
                        return;
                    }
                    try {
                        $member->allocateToDependant($dependent, (float) $amount, $data['notes'] ?? null, $data['allocation_date'] ?? null);
                        $this->record = $member->fresh();
                        $this->refreshFormData(['bank_account_balance', 'fund_account_balance', 'outstanding_loans']);
                        $this->dispatch('refreshTransactions');
                        Notification::make()
                            ->title('Allocation completed')
                            ->body('$' . number_format($amount, 2) . ' allocated to ' . ($dependent->user?->name ?? "Member #{$dependent->id}") . '.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('contribute')
                ->label('')
                ->tooltip(fn() => (float) $member->bank_account_balance <= 0 ? 'No bank balance available to contribute' : 'Contribute')
                ->icon('heroicon-o-arrow-up-circle')
                ->link()
                ->visible(fn() => !$member->hasActiveLoan())
                ->disabled(fn() => (float) $member->bank_account_balance <= 0)
                ->form(function () use ($member) {
                    $default = (int) ($member->allowed_allocation ?? 500);
                    $bank = (float) $member->bank_account_balance;
                    return [
                        Forms\Components\TextInput::make('amount')
                            ->label('Contribution Amount')
                            ->numeric()
                            ->prefix('$')
                            ->default($default)
                            ->minValue(0.01)
                            ->step(0.01)
                            ->required()
                            ->helperText('Default is your allowance ($' . number_format($default, 2) . '). Bank balance available: $' . number_format($bank, 2) . '.'),
                        Forms\Components\DatePicker::make('contribution_date')
                            ->label('Contribution date (optional)')
                            ->native(false),
                    ];
                })
                ->action(function (array $data): void {
                    $member = $this->record->fresh();
                    $amount = (float) $data['amount'];
                    try {
                        $member->contribute($amount, null, $data['contribution_date'] ?? null);
                        $this->record = $member->fresh();
                        $this->refreshFormData(['bank_account_balance', 'fund_account_balance']);
                        $this->dispatch('refreshTransactions');
                        Notification::make()
                            ->title('Contribution posted')
                            ->body('$' . number_format($amount, 2) . ' contributed to your fund account.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('make_repayment')
                ->label('')
                ->tooltip('Make Repayment')
                ->icon('heroicon-o-arrow-up-circle')
                ->link()
                ->visible(fn() => $member->hasActiveLoan())
                ->form(function () use ($member) {
                    $activeLoans = $member->loans()->where('status', 'active')->with('member')->get();
                    $options = $activeLoans->mapWithKeys(function (\App\Models\Loan $loan) {
                        $installment = (float) ($loan->installment_amount ?? $loan->monthly_payment);
                        $remaining = (float) $loan->outstanding_balance;
                        $payAmount = min($installment, $remaining);
                        return [
                            $loan->id => "{$loan->loan_id} — installment \${$payAmount} (balance \$" . number_format($remaining, 2) . ')',
                        ];
                    });
                    return [
                        Forms\Components\Select::make('loan_id')
                            ->label('Loan')
                            ->options($options)
                            ->required()
                            ->default($activeLoans->first()?->id)
                            ->live()
                            ->helperText(function ($get) use ($member, $activeLoans) {
                                $loan = $activeLoans->find((int) $get('loan_id'));
                                if (!$loan) {
                                    return null;
                                }
                                $installment = min((float) ($loan->installment_amount ?? $loan->monthly_payment), (float) $loan->outstanding_balance);
                                $bank = (float) $member->bank_account_balance;
                                $sufficient = $bank >= $installment;
                                return 'Repayment: $' . number_format($installment, 2)
                                    . ' — bank balance: $' . number_format($bank, 2)
                                    . ($sufficient ? '' : ' — Insufficient balance');
                            }),
                    ];
                })
                ->action(function (array $data): void {
                    $member = $this->record->fresh();
                    $loan = \App\Models\Loan::find($data['loan_id']);
                    if (!$loan || $loan->member_id !== $member->id) {
                        Notification::make()->title('Invalid loan')->danger()->send();
                        return;
                    }
                    try {
                        $member->makeRepayment($loan);
                        $this->record = $member->fresh();
                        $this->refreshFormData(['bank_account_balance', 'fund_account_balance', 'outstanding_loans']);
                        $this->dispatch('refreshTransactions');
                        Notification::make()
                            ->title('Repayment posted')
                            ->body("Loan {$loan->loan_id}: repayment recorded.")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('request_loan')
                ->label('')
                ->tooltip('Request Loan')
                ->icon('heroicon-o-banknotes')
                ->link()
                ->form(function () use ($member) {
                    $maxLoan = $member->maxLoanAmount();
                    $errors = $member->loanEligibilityErrors();
                    $fields = [];

                    if (!empty($errors)) {
                        $fields[] = Forms\Components\Placeholder::make('_eligibility_warning')
                            ->label('Not Eligible')
                            ->content(implode("\n", $errors))
                            ->extraAttributes(['class' => 'text-danger-600 dark:text-danger-400']);
                    }

                    $fields[] = Forms\Components\Placeholder::make('_info')
                        ->label('Loan Limits')
                        ->content('Max loan: $' . number_format($maxLoan, 2) . ' (2× fund balance $' . number_format((float) $member->fund_account_balance, 2) . ')');

                    $fields[] = Forms\Components\TextInput::make('amount')
                        ->label('Loan Amount')
                        ->numeric()
                        ->prefix('$')
                        ->required()
                        ->minValue(1000)
                        ->maxValue(min($maxLoan, 300000))
                        ->step(1)
                        ->live()
                        ->helperText(function ($get) {
                            $amount = (float) ($get('amount') ?? 0);
                            $tier = Member::loanTierFor($amount);
                            if (!$tier) {
                                return $amount > 0 ? 'Amount outside tier range ($1,000–$300,000)' : null;
                            }
                            return 'Installment: $' . number_format($tier['installment'])
                                . ' | Maturity balance: $' . number_format($tier['maturity_balance']);
                        });

                    $fields[] = Forms\Components\TextInput::make('interest_rate')
                        ->label('Annual Interest Rate (%)')
                        ->numeric()
                        ->default(0)
                        ->suffix('%')
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.01);

                    $fields[] = Forms\Components\Toggle::make('is_emergency')
                        ->label('Emergency Request')
                        ->default(false);

                    $fields[] = Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->rows(2);

                    return $fields;
                })
                ->action(function (array $data): void {
                    $member = $this->record->fresh();
                    $errors = $member->loanEligibilityErrors();
                    if (!empty($errors)) {
                        Notification::make()->title('Not eligible for a loan')->body(implode("\n", $errors))->danger()->send();
                        return;
                    }
                    $amount = (float) $data['amount'];
                    if ($amount > $member->maxLoanAmount()) {
                        Notification::make()->title('Amount exceeds maximum ($' . number_format($member->maxLoanAmount(), 2) . ')')->danger()->send();
                        return;
                    }
                    $tier = Member::loanTierFor($amount);
                    $loan = \App\Models\Loan::create([
                        'loan_id' => \App\Models\Loan::generateLoanId(),
                        'user_id' => $member->user_id,
                        'member_id' => $member->id,
                        'origination_date' => now(),
                        'original_amount' => $amount,
                        'interest_rate' => (float) ($data['interest_rate'] ?? 0),
                        'term_months' => $tier ? (int) ceil($amount / $tier['installment']) : 12,
                        'monthly_payment' => $tier['installment'] ?? $amount,
                        'installment_amount' => $tier['installment'] ?? null,
                        'maturity_fund_balance' => $tier['maturity_balance'] ?? null,
                        'total_paid' => 0,
                        'outstanding_balance' => $amount,
                        'status' => 'pending',
                        'is_emergency' => (bool) ($data['is_emergency'] ?? false),
                        'notes' => $data['notes'] ?? null,
                    ]);
                    Notification::make()
                        ->title('Loan request submitted')
                        ->body("Loan {$loan->loan_id} for \$" . number_format($amount, 2) . ' is pending approval.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('import_contributions')
                ->label('')
                ->tooltip('Import contributions')
                ->icon('heroicon-o-banknotes')
                ->link()
                ->form([
                    Forms\Components\FileUpload::make('import_file')
                        ->label('File (CSV only)')
                        ->helperText('Two columns only: Transaction Date (A), Amount (B). First row is the header.')
                        ->required()
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                        ->rules(['mimes:csv,txt'])
                        ->disk('local')
                        ->directory('imports/member-contributions')
                        ->visibility('private'),
                    Forms\Components\Select::make('date_format')
                        ->label('Date format')
                        ->required()
                        ->options(MemberResource::memberImportDateFormatOptions())
                        ->default('Y-m-d')
                        ->helperText('Must match the date column in your file.'),
                ])
                ->action(function (array $data): void {
                    $member = $this->record->fresh();
                    $path = $data['import_file'];
                    $absolutePath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);
                    try {
                        $results = $member->importContributions($absolutePath, $data['date_format'] ?? 'Y-m-d');
                        $this->record = $member->fresh();
                        $this->refreshFormData(['bank_account_balance', 'fund_account_balance']);
                        $this->dispatch('refreshTransactions');
                        \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
                        MemberResource::notifyMemberImportResults('Contributions import complete', $results);
                    } catch (\Exception $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('import_loan_repayments')
                ->label('')
                ->tooltip('Import loan repayments')
                ->icon('heroicon-o-arrow-uturn-left')
                ->link()
                ->form([
                    Forms\Components\Select::make('loan_id')
                        ->label('Loan')
                        ->required()
                        ->searchable()
                        ->options(fn (): array => $this->record->loans()
                            ->where('status', 'active')
                            ->orderBy('origination_date')
                            ->orderBy('id')
                            ->get()
                            ->mapWithKeys(fn (Loan $l) => [
                                $l->id => $l->loan_id . ' — outstanding $' . number_format((float) $l->outstanding_balance, 2),
                            ])
                            ->all())
                        ->helperText('The loan is not in the file — pick it here. Every row will post a repayment to this loan.'),
                    Forms\Components\FileUpload::make('import_file')
                        ->label('File (CSV only)')
                        ->helperText('Two columns only: Transaction Date (A), Amount (B). First row is the header.')
                        ->required()
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                        ->rules(['mimes:csv,txt'])
                        ->disk('local')
                        ->directory('imports/member-repayments')
                        ->visibility('private'),
                    Forms\Components\Select::make('date_format')
                        ->label('Date format')
                        ->required()
                        ->options(MemberResource::memberImportDateFormatOptions())
                        ->default('Y-m-d')
                        ->helperText('Must match the date column in your file.'),
                ])
                ->action(function (array $data): void {
                    $member = $this->record->fresh();
                    $loan = Loan::query()
                        ->whereKey($data['loan_id'])
                        ->where('member_id', $member->id)
                        ->first();
                    if (! $loan) {
                        Notification::make()->title('Invalid loan')->body('Choose an active loan for this member.')->danger()->send();

                        return;
                    }
                    $path = $data['import_file'];
                    $absolutePath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);
                    try {
                        $results = $member->importLoanRepayments($absolutePath, $data['date_format'] ?? 'Y-m-d', $loan);
                        $this->record = $member->fresh();
                        $this->refreshFormData(['bank_account_balance', 'fund_account_balance', 'outstanding_loans']);
                        $this->dispatch('refreshTransactions');
                        \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
                        MemberResource::notifyMemberImportResults("Repayments import complete ({$loan->loan_id})", $results);
                    } catch (\Exception $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('recalculate_balance')
                ->label('')
                ->tooltip('Recalculate Balances')
                ->icon('heroicon-o-calculator')
                ->link()
                ->requiresConfirmation()
                ->modalHeading('Recalculate balances from transactions')
                ->modalDescription('This will recalculate both the Bank Account Balance and Fund Account Balance from the member\'s transactions.')
                ->action(function (): void {
                    /** @var Member $member */
                    $member = $this->record;
                    $bankBalance = $member->recalculateBankAccountBalanceFromTransactions();
                    $fundBalance = $member->recalculateFundAccountBalanceFromTransactions();
                    $member->updateOutstandingLoans();
                    $this->record = $member->fresh();
                    $this->refreshFormData(['bank_account_balance', 'fund_account_balance', 'outstanding_loans']);
                    Notification::make()
                        ->title('Balances recalculated')
                        ->body('Bank: $' . number_format($bankBalance, 2) . ' | Fund: $' . number_format($fundBalance, 2))
                        ->success()
                        ->send();
                }),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
