<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoanResource\Pages;
use App\Models\Loan;
use App\Models\Member;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Schemas\Components;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

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
                Components\Section::make('Loan Information')
                    ->schema([
                        Forms\Components\TextInput::make('loan_id')
                            ->default(fn () => Loan::generateLoanId())
                            ->disabled()
                            ->dehydrated()
                            ->required(),

                        Forms\Components\Select::make('member_id')
                            ->label('Member')
                            ->options(fn () => Member::with('user')->get()->mapWithKeys(
                                fn (Member $m) => [$m->id => $m->user ? "{$m->user->name} ({$m->user->user_code})" : "Member #{$m->id}"]
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
                            }),

                        Forms\Components\Hidden::make('user_id'),

                        Forms\Components\Placeholder::make('_fund_balance')
                            ->label('Fund Account Balance')
                            ->content(fn (Get $get) => '$' . number_format((float) ($get('_fund_balance') ?? 0), 2)),

                        Forms\Components\Placeholder::make('_max_loan')
                            ->label('Maximum Loan Amount (2× fund)')
                            ->content(fn (Get $get) => '$' . number_format((float) ($get('_max_loan') ?? 0), 2)),

                        Forms\Components\Placeholder::make('_eligibility_errors')
                            ->label('Eligibility')
                            ->content(function (Get $get) {
                                $err = $get('_eligibility_errors');
                                return $err ? $err : '✓ Eligible';
                            })
                            ->extraAttributes(fn (Get $get) => $get('_eligibility_errors')
                                ? ['class' => 'text-danger-600 dark:text-danger-400']
                                : ['class' => 'text-success-600 dark:text-success-400']),

                        Forms\Components\DatePicker::make('origination_date')
                            ->default(now())
                            ->required(),

                        Forms\Components\TextInput::make('original_amount')
                            ->label('Loan Amount')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->step(1)
                            ->minValue(1000)
                            ->maxValue(300000)
                            ->reactive()
                            ->helperText(function (Get $get) {
                                $amount = (float) ($get('original_amount') ?? 0);
                                $tier = Member::loanTierFor($amount);
                                if (! $tier) {
                                    return $amount > 0 ? 'Amount outside loan tier range ($1,000–$300,000)' : null;
                                }
                                return 'Tier: $' . number_format($tier['installment']) . '/installment, maturity balance: $' . number_format($tier['maturity_balance']);
                            }),

                        Forms\Components\TextInput::make('interest_rate')
                            ->label('Annual Interest Rate (%)')
                            ->numeric()
                            ->required()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default(0),

                        Forms\Components\TextInput::make('term_months')
                            ->label('Term (Months)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(360)
                            ->default(12),

                        Forms\Components\Toggle::make('is_emergency')
                            ->label('Emergency Request')
                            ->helperText('Emergency loans are prioritised for approval.')
                            ->default(false),

                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending Approval',
                                'active' => 'Active',
                                'paid_off' => 'Paid Off',
                                'defaulted' => 'Defaulted',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('pending')
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
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
                    ->color(fn ($record) => $record->isDelinquent() ? 'danger' : null),
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
                    ->query(fn ($query) => $query->where('status', 'active')
                        ->where('next_payment_date', '<', now())),

                Tables\Filters\Filter::make('due_soon')
                    ->label('Due in 7 Days')
                    ->query(fn ($query) => $query->where('status', 'active')
                        ->whereBetween('next_payment_date', [now(), now()->addDays(7)])),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make()
                    ->visible(fn ($record) => $record->status === 'pending'),

                Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Loan $record) => 'Approve loan ' . $record->loan_id . '?')
                    ->modalDescription(function (Loan $record) {
                        $tier = Member::loanTierFor((float) $record->original_amount);
                        $desc = 'Amount: $' . number_format((float) $record->original_amount, 2);
                        if ($tier) {
                            $desc .= "\nInstallment: \$" . number_format($tier['installment'], 2)
                                . "\nMaturity fund balance: \$" . number_format($tier['maturity_balance'], 2);
                        }
                        return $desc;
                    })
                    ->action(function (Loan $record) {
                        try {
                            $record->approve(auth()->id());
                            Notification::make()
                                ->title('Loan approved and disbursed')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    })
                    ->visible(fn ($record) => $record->status === 'pending'),

                Actions\Action::make('view_schedule')
                    ->icon('heroicon-o-calendar')
                    ->color('info')
                    ->modalHeading('Amortization Schedule')
                    ->modalContent(fn ($record) => view('filament.modals.amortization-schedule', [
                        'schedule' => $record->generateAmortizationSchedule(),
                    ]))
                    ->modalSubmitAction(false),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
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
                            ->color(fn ($record) => $record->outstanding_balance > 0 ? 'warning' : 'success'),
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
                            ->color(fn ($record) => $record->isDelinquent() ? 'danger' : null),
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
                            ->state(fn ($record) => $record->payments()->count()),
                        Infolists\Components\TextEntry::make('remaining_term')
                            ->suffix(' months'),
                    ])
                    ->columns(3),

                Components\Section::make('Maturity Status')
                    ->schema([
                        Infolists\Components\IconEntry::make('is_matured')
                            ->label('Loan Matured')
                            ->getStateUsing(fn (Loan $record) => $record->isMatured())
                            ->boolean(),
                        Infolists\Components\TextEntry::make('maturity_fund_target')
                            ->label('Required Fund Balance')
                            ->getStateUsing(fn (Loan $record) => '$' . number_format((float) ($record->maturity_fund_balance ?? 0), 2)),
                        Infolists\Components\TextEntry::make('current_fund_balance')
                            ->label('Current Fund Balance')
                            ->getStateUsing(fn (Loan $record) => $record->member
                                ? '$' . number_format((float) $record->member->fund_account_balance, 2)
                                : '—'),
                    ])
                    ->columns(3)
                    ->visible(fn (Loan $record) => in_array($record->status, ['active', 'paid_off'])),

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
                    ->visible(fn ($record) => $record->approved_by),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
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
