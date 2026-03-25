<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoanResource\Pages;
use App\Models\Loan;
use App\Models\Member;
use App\Services\LoanService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Schemas\Components;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Livewire\Livewire;

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static string|\UnitEnum|null $navigationGroup = 'Financial Operations';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('Member')
                    ->description('Select the member applying for the loan. Eligibility is based on fund balance and tier rules.')
                    ->icon('heroicon-o-user-plus')
                    ->schema([
                        Forms\Components\Select::make('member_id')
                            ->label('Member')
                            ->options(fn() => Member::with('user')->get()->mapWithKeys(
                                fn(Member $m) => [$m->id => $m->user ? "{$m->user->name} ({$m->user->user_code})" : "Member #{$m->id}"]
                            ))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $member = Member::find($state);
                                    if ($member) {
                                        $set('user_id', $member->user_id);
                                        $set('_fund_balance', (float) $member->fund_account_balance);
                                        $set('_max_loan', $member->maxLoanAmount());
                                        $set('_eligibility_errors', implode("\n", $member->loanEligibilityErrors()));
                                    }
                                }
                            })
                            ->columnSpanFull(),

                        Forms\Components\Hidden::make('user_id'),

                        Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('_fund_balance_display')
                                    ->label('Fund balance')
                                    ->state(fn (Get $get) => '$' . number_format((float) ($get('_fund_balance') ?? 0), 2)),
                                Infolists\Components\TextEntry::make('_max_loan_display')
                                    ->label('Max loan (2× fund)')
                                    ->state(fn (Get $get) => '$' . number_format((float) ($get('_max_loan') ?? 0), 2)),
                                Infolists\Components\TextEntry::make('_eligibility_errors_display')
                                    ->label('Eligibility')
                                    ->state(function (Get $get) {
                                        $err = $get('_eligibility_errors');
                                        return $err ?: '✓ Eligible';
                                    })
                                    ->extraAttributes(fn (Get $get) => $get('_eligibility_errors')
                                        ? ['class' => 'text-danger-600 dark:text-danger-400 font-medium']
                                        : ['class' => 'text-success-600 dark:text-success-400 font-medium']),
                            ])
                            ->columnSpanFull(),
                        Forms\Components\Hidden::make('_fund_balance'),
                        Forms\Components\Hidden::make('_max_loan'),
                        Forms\Components\Hidden::make('_eligibility_errors'),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Components\Section::make('Loan amount & tier')
                    ->description('Enter the loan amount. Terms (installment, maturity target) are set by the loan tier for that amount.')
                    ->icon('heroicon-o-currency-dollar')
                    ->schema([
                        Forms\Components\TextInput::make('original_amount')
                            ->label('Loan amount')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->step(1)
                            ->minValue(1000)
                            ->maxValue(300000)
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set) {
                                $amount = $state !== null && $state !== '' ? round((float) $state, 0) : 0;
                                if ($amount > 0 && abs((float) $state - $amount) > 0.001) {
                                    $set('original_amount', $amount);
                                }
                                $tier = $amount > 0 ? Member::loanTierFor($amount) : null;
                                if ($tier) {
                                    $set('term_months', (int) ($tier['term_months'] ?? 12));
                                    $set('interest_rate', (float) ($tier['interest_rate'] ?? 0));
                                }
                            })
                            ->placeholder('e.g. 15000')
                            ->rule(function (Get $get) {
                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if ($get('override_allocation_limit')) {
                                        return;
                                    }
                                    $memberId = (int) $get('member_id');
                                    if ($memberId) {
                                        $member = Member::find($memberId);
                                        if ($member) {
                                            $error = $member->checkTierAllocation((float) $value);
                                            if ($error) {
                                                $fail($error);
                                            }
                                        }
                                    }
                                };
                            }),

                        Infolists\Components\TextEntry::make('_tier_summary')
                            ->label('Tier terms')
                            ->state(function (Get $get) {
                                $amount = (float) ($get('original_amount') ?? 0);
                                if ($amount <= 0) {
                                    return 'Enter an amount between $1,000 and $300,000.';
                                }
                                $tier = Member::loanTierFor($amount);
                                if (!$tier) {
                                    return 'Amount outside tier range ($1,000–$300,000).';
                                }
                                $pct = (float) ($tier['maturity_percentage'] ?? 16);
                                return 'Installment: $' . number_format($tier['installment_amount']) . ' / month · Maturity target: ' . $pct . '% ($' . number_format($tier['maturity_balance']) . ')';
                            })
                            ->visible(fn (Get $get) => (float) ($get('original_amount') ?? 0) > 0),

                        Infolists\Components\TextEntry::make('_term_display')
                            ->label('Loan term (months)')
                            ->state(function (Get $get) {
                                $amount = (float) ($get('original_amount') ?? 0);
                                if ($amount <= 0) {
                                    return '—';
                                }
                                $tier = Member::loanTierFor($amount);
                                if (!$tier) {
                                    return '—';
                                }
                                return (string) ($tier['term_months'] ?? '—');
                            })
                            ->helperText('Auto-calculated: number of installments to cover 50% + 16% of the loan amount (payoff rule).')
                            ->visible(fn (Get $get) => (float) ($get('original_amount') ?? 0) > 0),

                        Forms\Components\Hidden::make('term_months')->default(12)->dehydrated(),
                        Forms\Components\Hidden::make('interest_rate')->default(0)->dehydrated(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Components\Section::make('Disbursement')
                    ->description('When and how the loan will be paid out. One date or multiple parts; repayment starts the cycle after the last disbursement.')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        Forms\Components\DatePicker::make('origination_date')
                            ->label('Origination date')
                            ->default(now())
                            ->required()
                            ->minDate(fn(Get $get) => $get('force_create_old_loan') ? null : now()),

                        Forms\Components\Toggle::make('force_create_old_loan')
                            ->label('Backdated loan (allow past dates)')
                            ->helperText('Use for loans that start in the past. If you leave the schedule empty, a single disbursement on the origination date will be used.')
                            ->default(false)
                            ->reactive(),

                        Forms\Components\Repeater::make('disbursement_schedule')
                            ->label('Disbursement schedule (optional)')
                            ->helperText('Leave empty for one disbursement at approval. Or add rows: each part has a date and amount; the total must equal the loan amount.')
                            ->schema([
                                Forms\Components\DatePicker::make('disbursement_date')->label('Date')->required(),
                                Forms\Components\TextInput::make('amount')
                                    ->label('Amount')
                                    ->required()
                                    ->prefix('$')
                                    ->placeholder('e.g. 50000')
                                    ->rules(['numeric', 'min:1']),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->live()
                            ->rule(function (Get $get) {
                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if (! is_array($value) || count($value) === 0) {
                                        return;
                                    }

                                    // Create + pending edit: schedule parts must equal the loan amount.
                                    // Active/paid-off/etc.: do not block saves when legacy or partial tranches
                                    // left the stored schedule sum ≠ original_amount (still fixable in data if needed).
                                    $livewire = Livewire::current();
                                    if ($livewire instanceof EditRecord) {
                                        $record = $livewire->getRecord();
                                        if ($record instanceof Loan && $record->status !== 'pending') {
                                            return;
                                        }
                                    }

                                    $total = 0.0;
                                    foreach ($value as $row) {
                                        $total += (float) ($row['amount'] ?? 0);
                                    }
                                    $loanAmount = (float) ($get('original_amount') ?? 0);
                                    if ($loanAmount <= 0) {
                                        return;
                                    }
                                    if (abs($total - $loanAmount) > 0.01) {
                                        $fail("Schedule total (\$" . number_format($total, 2) . ") must equal the loan amount (\$" . number_format($loanAmount, 2) . ").");
                                    }
                                };
                            }),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Components\Section::make('Options & notes')
                    ->description('Priority and status. Notes are for internal use.')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Forms\Components\Toggle::make('override_allocation_limit')
                            ->label('Override tier allocation limit')
                            ->helperText('Allow this loan even if the tier allocation (e.g. 10% of Master Fund for this tier) would be exceeded. Use when you have approved an exception.')
                            ->default(false),

                        Forms\Components\Toggle::make('is_emergency')
                            ->label('Emergency request')
                            ->helperText('Prioritised in the approval queue.')
                            ->default(false),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Pending approval',
                                'active' => 'Active',
                                'paid_off' => 'Paid off',
                                'defaulted' => 'Defaulted',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('pending')
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->placeholder('Internal notes about this application.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Forms\Components\Hidden::make('loan_id')
                    ->default(fn() => Loan::generateLoanId())
                    ->dehydrated(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('loan_id')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('member.user.name')
                    ->label('Member')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_emergency')
                    ->label('Emergency')
                    ->boolean()
                    ->trueColor('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('origination_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('original_amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('installment_amount')
                    ->label('Installment')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('outstanding_balance')
                    ->label('Balance')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD')
                            ->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'active',
                        'primary' => 'paid_off',
                        'danger' => 'defaulted',
                        'secondary' => 'cancelled',
                    ])
                    ->badge(),

                Tables\Columns\TextColumn::make('next_payment_date')
                    ->label('Next Payment')
                    ->date()
                    ->sortable()
                    ->color(fn($record) => $record->isDelinquent() ? 'danger' : null),

                Tables\Columns\TextColumn::make('maturity_date')
                    ->label('Maturity date')
                    ->date()
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending Approval',
                        'active' => 'Active',
                        'paid_off' => 'Paid Off',
                        'defaulted' => 'Defaulted',
                        'cancelled' => 'Cancelled',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('member')
                    ->relationship('member.user', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_emergency')
                    ->label('Emergency'),

                Tables\Filters\Filter::make('delinquent')
                    ->label('Delinquent')
                    ->query(fn($query) => $query->where('status', 'active')
                        ->where('next_payment_date', '<', now())),

                Tables\Filters\Filter::make('due_soon')
                    ->label('Due in 7 Days')
                    ->query(fn($query) => $query->where('status', 'active')
                        ->whereBetween('next_payment_date', [now(), now()->addDays(7)])),
            ])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make()
                        ->label('View')
                        ->tooltip('View'),
                    Actions\EditAction::make()
                        ->label('Edit')
                        ->tooltip('Edit')
                        ->authorize(fn() => true),
                    Actions\Action::make('approve')
                        ->label('Approve')
                        ->tooltip('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->requiresConfirmation()
                        ->modalHeading(fn(Loan $record) => 'Approve loan ' . $record->loan_id . '?')
                        ->modalDescription(function (Loan $record) {
                            $tier = Member::loanTierFor((float) $record->original_amount);
                            $desc = 'Amount: $' . number_format((float) $record->original_amount, 2);
                            if ($tier) {
                                $percentage = (float) ($tier['maturity_percentage'] ?? 16);
                                $desc .= "\nInstallment: \$" . number_format($tier['installment_amount'], 2)
                                    . "\nMaturity target (" . $percentage . "%): \$" . number_format($tier['maturity_balance'], 2);
                            }
                            return $desc;
                        })
                        ->action(function (Loan $record) {
                            try {
                                $member = $record->member;
                                if ($member) {
                                    $error = $member->checkTierAllocation((float) $record->original_amount);
                                    if ($error) {
                                        throw new \Exception($error);
                                    }
                                }

                                app(LoanService::class)->approveLoan($record);
                                Notification::make()
                                    ->title('Loan approved and disbursed')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()->title($e->getMessage())->danger()->send();
                            }
                        })
                        ->visible(fn($record) => $record->status === 'pending'),

                    Actions\Action::make('view_schedule')
                        ->label('View Schedule')
                        ->tooltip('View Amortization Schedule')
                        ->icon('heroicon-o-calendar')
                        ->modalHeading('Amortization Schedule')
                        ->modalContent(fn($record) => view('filament.modals.amortization-schedule', [
                            'schedule' => $record->generateAmortizationSchedule(),
                        ]))
                        ->modalSubmitAction(false),
                ])
                    ->label('')
                    ->icon('heroicon-o-ellipsis-horizontal'),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->emptyStateHeading('No loans yet')
            ->emptyStateDescription('Loan applications will appear here once created.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('Loan Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('loan_id')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('member.user.name')
                            ->label('Member'),
                        Infolists\Components\TextEntry::make('origination_date')
                            ->date(),
                        Infolists\Components\TextEntry::make('fully_disbursed_date')
                            ->label('Fully disbursed date')
                            ->date()
                            ->placeholder('—')
                            ->visible(fn(Loan $record) => $record->fully_disbursed_date !== null),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn($state) => match ($state) {
                                'pending' => 'warning',
                                'active' => 'success',
                                'paid_off' => 'primary',
                                'defaulted' => 'danger',
                                'cancelled' => 'secondary',
                                default => 'secondary',
                            }),
                        Infolists\Components\IconEntry::make('is_emergency')
                            ->label('Emergency')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('original_amount')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('outstanding_balance')
                            ->money('USD')
                            ->color(fn($record) => $record->outstanding_balance > 0 ? 'warning' : 'success'),
                        Infolists\Components\TextEntry::make('interest_rate')
                            ->suffix('%'),
                        Infolists\Components\TextEntry::make('term_months')
                            ->suffix(' months'),
                        Infolists\Components\TextEntry::make('installment_amount')
                            ->label('Installment Amount')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('maturity_fund_balance')
                            ->label('Maturity Fund Balance')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('next_payment_date')
                            ->date()
                            ->color(fn($record) => $record->isDelinquent() ? 'danger' : null),
                        Infolists\Components\TextEntry::make('maturity_date')
                            ->label('Maturity date')
                            ->date()
                            ->placeholder('—'),
                    ])
                    ->columns(2),

                Components\Section::make('Payment Progress')
                    ->schema([
                        Infolists\Components\TextEntry::make('total_paid')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('total_principal_paid')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('total_interest_paid')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('progress_percentage')
                            ->suffix('%')
                            ->color('success'),
                        Infolists\Components\TextEntry::make('payments')
                            ->label('Payments Made')
                            ->state(fn($record) => $record->payments()->count()),
                        Infolists\Components\TextEntry::make('remaining_term')
                            ->suffix(' months'),
                    ])
                    ->columns(3),

                Components\Section::make('Maturity Status')
                    ->schema([
                        Infolists\Components\IconEntry::make('is_matured')
                            ->label('Loan Matured')
                            ->getStateUsing(fn(Loan $record) => $record->isMatured())
                            ->boolean(),
                        Infolists\Components\TextEntry::make('maturity_fund_target')
                            ->label('Required Fund Balance')
                            ->getStateUsing(fn(Loan $record) => '$' . number_format((float) ($record->maturity_fund_balance ?? 0), 2)),
                        Infolists\Components\TextEntry::make('current_fund_balance')
                            ->label('Current Fund Balance')
                            ->getStateUsing(fn(Loan $record) => $record->member
                                ? '$' . number_format((float) $record->member->fund_account_balance, 2)
                                : '—'),
                    ])
                    ->columns(3)
                    ->visible(fn(Loan $record) => in_array($record->status, ['active', 'paid_off'])),

                Components\Section::make('Approval Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('approver.name')
                            ->label('Approved By'),
                        Infolists\Components\TextEntry::make('approved_at')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn($record) => $record->approved_by),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\LoanResource\RelationManagers\LoanPaymentsRelationManager::class,
            \App\Filament\Resources\LoanResource\RelationManagers\TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoans::route('/'),
            'create' => Pages\CreateLoan::route('/create'),
            'view' => Pages\ViewLoan::route('/{record}'),
            'edit' => Pages\EditLoan::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::where('status', 'pending')->count();
        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
