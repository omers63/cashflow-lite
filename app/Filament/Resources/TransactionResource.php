<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
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
use Filament\Schemas\Components\Utilities\Get;

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
                            ])
                            ->required()
                            ->reactive(),

                        Forms\Components\TextInput::make('from_account')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('to_account')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->step(0.01)
                            ->minValue(0.01),

                        Forms\Components\Select::make('user_id')
                            ->label('User')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(fn(Get $get) => in_array($get('type'), [
                                'master_to_user_bank',
                                'contribution',
                                'loan_repayment',
                                'loan_disbursement'
                            ])),

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
                    ->columns(2),
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
                        'success' => 'contribution',
                        'warning' => 'loan_repayment',
                        'danger' => 'loan_disbursement',
                        'info' => 'master_to_user_bank',
                        'secondary' => 'adjustment',
                    ])
                    ->formatStateUsing(fn($state) => str_replace('_', ' ', ucfirst($state))),

                Tables\Columns\TextColumn::make('from_account')
                    ->limit(20)
                    ->tooltip(fn($record) => $record->from_account),

                Tables\Columns\TextColumn::make('to_account')
                    ->limit(20)
                    ->tooltip(fn($record) => $record->to_account),

                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
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
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'external_import' => 'External Bank Import',
                        'master_to_user_bank' => 'Master to User Bank',
                        'contribution' => 'Contribution',
                        'loan_repayment' => 'Loan Repayment',
                        'loan_disbursement' => 'Loan Disbursement',
                        'adjustment' => 'Adjustment',
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

                Tables\Filters\Filter::make('transaction_date')
                    ->schema([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('to')
                            ->label('To Date'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn($q, $date) => $q->whereDate('transaction_date', '>=', $date))
                            ->when($data['to'], fn($q, $date) => $q->whereDate('transaction_date', '<=', $date));
                    }),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make()
                    ->visible(fn($record) => $record->status === 'pending'),

                Actions\Action::make('assign_user')
                    ->label('Assign to User Bank')
                    ->icon('heroicon-o-user-plus')
                    ->color('gray')
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('User Bank Account')
                            ->options(User::active()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Transaction $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $user = User::find($data['user_id']);
                            if ($user) {
                                $user->creditBankAccount((float) $record->amount);
                            }
                            $record->update(['user_id' => $data['user_id']]);
                        });
                        \Filament\Notifications\Notification::make()
                            ->title('Assignment updated')
                            ->success()
                            ->send();
                    })
                    ->visible(fn(Transaction $record) => $record->type === 'external_import' && ! $record->user_id),

                Actions\Action::make('clear_assignment')
                    ->label('Clear Assignment')
                    ->icon('heroicon-o-user-minus')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Clear user assignment')
                    ->modalDescription('This will unassign the transaction from the current user.')
                    ->action(function (Transaction $record): void {
                        DB::transaction(function () use ($record): void {
                            $user = $record->user;
                            if ($user) {
                                $user->debitBankAccount((float) $record->amount);
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
            ]);
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
                                'contribution' => 'success',
                                'loan_repayment' => 'warning',
                                'loan_disbursement' => 'danger',
                                'master_to_user_bank' => 'info',
                                'adjustment' => 'secondary',
                                default => 'secondary',
                            })
                            ->formatStateUsing(fn($state) => str_replace('_', ' ', ucfirst($state))),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn($state) => match ($state) {
                                'pending' => 'warning',
                                'complete' => 'success',
                                'failed' => 'danger',
                                'reversed' => 'secondary',
                                default => 'secondary',
                            }),
                        Infolists\Components\TextEntry::make('from_account'),
                        Infolists\Components\TextEntry::make('to_account'),
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
