<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;
use Filament\Forms\Get;
use Filament\Forms\Set;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';
    protected static string|\UnitEnum|null $navigationGroup = 'Financial Operations';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('Transaction Details')
                    ->schema([
                        Forms\Components\TextInput::make('transaction_id')
                            ->default(fn() => Transaction::generateTransactionId())
                            ->disabled()
                            ->dehydrated()
                            ->required(),

                        Forms\Components\DateTimePicker::make('transaction_date')
                            ->default(now())
                            ->required(),

                        Forms\Components\Select::make('type')
                            ->options([
                                'external_import' => 'External Bank Import',
                                'master_to_user_bank' => 'Master to User Bank',
                                'contribution' => 'Contribution',
                                'loan_repayment' => 'Loan Repayment',
                                'loan_disbursement' => 'Loan Disbursement',
                                'adjustment' => 'Adjustment',
                                'credit' => 'Credit (Add Funds)',
                                'debit' => 'Debit (Deduct Funds)',
                            ])
                            ->required()
                            ->reactive(),

                        Forms\Components\Select::make('target_account')
                            ->label('Target Account')
                            ->options([
                                'master_bank' => 'Master Bank',
                                'master_fund' => 'Master Fund',
                                'user_bank' => 'Member Bank',
                                'user_fund' => 'Member Fund',
                                'external_bank' => 'External Bank',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('external_bank_account_id', null)),

                        Forms\Components\Select::make('external_bank_account_id')
                            ->label('External Bank Account')
                            ->options(\App\Models\ExternalBankAccount::pluck('bank_name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(fn($get) => $get('target_account') === 'external_bank')
                            ->visible(fn($get) => $get('target_account') === 'external_bank')
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state) {
                                    $set('target_account', "external_bank:{$state}");
                                }
                            }),

                        Forms\Components\Select::make('user_id')
                            ->label('User')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(fn ($get) => in_array($get('target_account'), ['user_bank', 'user_fund']))
                            ->visible(fn ($get) => in_array($get('target_account'), ['user_bank', 'user_fund'])),

                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->step(0.01)
                            ->minValue(0.01),

                        Forms\Components\TextInput::make('reference')
                            ->maxLength(255)
                            ->helperText('External Ref ID, Loan ID, etc.'),

                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'complete' => 'Complete',
                                'failed' => 'Failed',
                                'reversed' => 'Reversed',
                            ])
                            ->default('pending')
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_id')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('transaction_date')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'primary' => 'external_import',
                        'success' => ['contribution', 'credit'],
                        'warning' => 'loan_repayment',
                        'danger' => ['loan_disbursement', 'debit'],
                        'info' => 'master_to_user_bank',
                        'secondary' => 'adjustment',
                        'gray' => ['allocation_to_dependant', 'allocation_from_parent'],
                    ])
                    ->formatStateUsing(fn($state) => str_replace('_', ' ', ucfirst($state))),

                Tables\Columns\TextColumn::make('target_account')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn($state) => str_replace('_', ' ', ucfirst($state))),

                Tables\Columns\TextColumn::make('amount')
                    ->state(function (Transaction $record) {
                        $isCredit = $record->isCreditType($record->type);
                        if ($record->type === 'adjustment') {
                            $isCredit = $record->amount >= 0;
                        }
                        return $isCredit ? $record->amount : -$record->amount;
                    })
                    ->money('USD')
                    ->color(function (Transaction $record) {
                        $isCredit = $record->isCreditType($record->type);
                        if ($record->type === 'adjustment') {
                            $isCredit = $record->amount >= 0;
                        }
                        return $isCredit ? 'success' : 'danger';
                    })
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('Total')
                            ->money('USD'),
                    ]),

                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'complete',
                        'danger' => 'failed',
                        'secondary' => 'reversed',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->recordClasses(function (Transaction $record) {
                $isCredit = $record->isCreditType($record->type);
                if ($record->type === 'adjustment') {
                    $isCredit = $record->amount >= 0;
                }

                if ($isCredit === true) {
                    return 'bg-success-50 dark:bg-success-950';
                }
                if ($isCredit === false) {
                    return 'bg-danger-50 dark:bg-danger-950';
                }
                return null;
            })
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'external_import' => 'External Bank Import',
                        'master_to_user_bank' => 'Master to User Bank',
                        'contribution' => 'Contribution',
                        'loan_repayment' => 'Loan Repayment',
                        'loan_disbursement' => 'Loan Disbursement',
                        'adjustment' => 'Adjustment',
                        'credit' => 'Credit (Add Funds)',
                        'debit' => 'Debit (Deduct Funds)',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'complete' => 'Complete',
                        'failed' => 'Failed',
                        'reversed' => 'Reversed',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('target_account')
                    ->options([
                        'master_bank' => 'Master Bank',
                        'master_fund' => 'Master Fund',
                        'user_bank' => 'Member Bank',
                        'user_fund' => 'Member Fund',
                    ]),

                Tables\Filters\Filter::make('transaction_date')
                    ->schema([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('to')
                            ->label('To Date'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn($q, $date) => $q->whereDate('transaction_date', '>=', $date))
                            ->when($data['to'] ?? null, fn($q, $date) => $q->whereDate('transaction_date', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (! empty($data['from'])) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['from'])->toFormattedDateString();
                        }
                        if (! empty($data['to'])) {
                            $indicators[] = 'To: ' . \Carbon\Carbon::parse($data['to'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),

                Actions\Action::make('assign_user')
                    ->label('Assign Member')
                    ->icon('heroicon-o-user-plus')
                    ->color('gray')
                    ->form([
                        Forms\Components\Select::make('member_id')
                            ->label('Member')
                            ->options(
                                Member::with('user')
                                    ->get()
                                    ->mapWithKeys(fn (Member $m) => [
                                        $m->id => $m->user
                                            ? "{$m->user->name} ({$m->user->user_code})"
                                            : "Member #{$m->id}",
                                    ])
                            )
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Transaction $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $member = Member::find($data['member_id']);
                            if ($member) {
                                $member->creditBankAccount((float) $record->amount);
                                $record->update(['user_id' => $member->user_id]);
                            }
                        });
                        \Filament\Notifications\Notification::make()
                            ->title('Assigned to member')
                            ->success()
                            ->send();
                    })
                    ->visible(fn(Transaction $record) => $record->type === 'external_import' && ! $record->user_id),

                Actions\Action::make('clear_assignment')
                    ->label('Clear Assignment')
                    ->icon('heroicon-o-user-minus')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Clear member assignment')
                    ->modalDescription('This will unassign the transaction from the current member.')
                    ->action(function (Transaction $record): void {
                        DB::transaction(function () use ($record): void {
                            $member = $record->user?->member;
                            if ($member) {
                                $member->debitBankAccount((float) $record->amount);
                            }
                            $record->update(['user_id' => null]);
                        });
                        \Filament\Notifications\Notification::make()
                            ->title('Assignment cleared')
                            ->success()
                            ->send();
                    })
                    ->visible(fn(Transaction $record) => $record->type === 'external_import' && $record->user_id),

                Actions\Action::make('reverse')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        Forms\Components\Textarea::make('reason')
                            ->required()
                            ->label('Reversal Reason'),
                    ])
                    ->action(function (Transaction $record, array $data) {
                        $record->reverse($data['reason']);
                    })
                    ->visible(fn($record) => $record->status === 'complete'),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->emptyStateHeading('No transactions yet')
            ->emptyStateDescription('Transactions will appear here once they are recorded.')
            ->emptyStateIcon('heroicon-o-arrow-path');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('Transaction Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('transaction_id')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('transaction_date')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('type')
                            ->badge()
                            ->color(fn($state) => match ($state) {
                                'external_import' => 'primary',
                                'contribution', 'credit' => 'success',
                                'loan_repayment' => 'warning',
                                'loan_disbursement', 'debit' => 'danger',
                                'master_to_user_bank' => 'info',
                                'adjustment' => 'secondary',
                                'allocation_to_dependant' => 'gray',
                                'allocation_from_parent' => 'gray',
                                default => 'secondary',
                            })
                            ->formatStateUsing(fn($state) => str_replace('_', ' ', ucfirst($state))),
                        Infolists\Components\TextEntry::make('target_account'),
                        Infolists\Components\TextEntry::make('amount')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('reference'),
                    ])
                    ->columns(2),

                Components\Section::make('User Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('User'),
                        Infolists\Components\TextEntry::make('user.user_code')
                            ->label('User Code'),
                    ])
                    ->columns(2)
                    ->visible(fn($record) => $record->user_id),

                Components\Section::make('Additional Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('creator.name')
                            ->label('Created By'),
                        Infolists\Components\TextEntry::make('approver.name')
                            ->label('Approved By')
                            ->visible(fn($record) => $record->approved_by),
                        Infolists\Components\TextEntry::make('approved_at')
                            ->dateTime()
                            ->visible(fn($record) => $record->approved_at),
                        Infolists\Components\TextEntry::make('notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'view' => Pages\ViewTransaction::route('/{record}'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
