<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';
    protected static string|\UnitEnum|null $navigationGroup = 'User Management';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\TextInput::make('user_code')
                            ->label('User Code')
                            ->default(fn() => User::generateUserCode())
                            ->disabled()
                            ->dehydrated()
                            ->required(),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(20),

                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'suspended' => 'Suspended',
                            ])
                            ->required()
                            ->default('active'),

                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required(fn(string $context) => $context === 'create')
                            ->dehydrated(fn($state) => filled($state))
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Components\Section::make('Account Balances')
                    ->schema([
                        Forms\Components\TextInput::make('bank_account_balance')
                            ->label('User Bank Account Balance')
                            ->numeric()
                            ->default(0)
                            ->prefix('$')
                            ->step(0.01)
                            ->disabled(fn (string $context) => $context === 'edit')
                            ->dehydrated(fn (string $context) => $context !== 'edit')
                            ->helperText(fn (string $context) => $context === 'edit'
                                ? 'Read-only in edit mode. Balances change via transactions.'
                                : 'Editable for balance adjustments and corrections'),

                        Forms\Components\TextInput::make('fund_account_balance')
                            ->label('User Fund Account Balance')
                            ->numeric()
                            ->default(0)
                            ->prefix('$')
                            ->step(0.01)
                            ->disabled(fn (string $context) => $context === 'edit')
                            ->dehydrated(fn (string $context) => $context !== 'edit')
                            ->helperText(fn (string $context) => $context === 'edit'
                                ? 'Read-only in edit mode. Balances change via transactions.'
                                : 'Editable for balance adjustments and corrections'),

                        Forms\Components\TextInput::make('outstanding_loans')
                            ->label('Outstanding Loans')
                            ->numeric()
                            ->default(0)
                            ->prefix('$')
                            ->step(0.01)
                            ->disabled(),

                        Forms\Components\TextInput::make('available_to_borrow')
                            ->label('Available to Borrow')
                            ->prefix('$')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn(?User $record) => $record
                                ? number_format($record->available_to_borrow, 2)
                                : '0.00')
                            ->visible(fn(string $context) => $context === 'edit'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_code')
                    ->label('User ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('bank_account_balance')
                    ->label('Bank Account')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD')
                            ->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('fund_account_balance')
                    ->label('Fund Account')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD')
                            ->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('outstanding_loans')
                    ->label('Loans')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD')
                            ->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('available_to_borrow')
                    ->label('Available')
                    ->money('USD')
                    ->sortable(query: function ($query, $direction) {
                        $query->orderByRaw("(fund_account_balance - outstanding_loans) {$direction}");
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'suspended' => 'danger',
                        default => 'secondary',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                    ]),

                Tables\Filters\Filter::make('has_loans')
                    ->label('Has Active Loans')
                    ->query(fn($query) => $query->where('outstanding_loans', '>', 0)),

                Tables\Filters\Filter::make('can_borrow')
                    ->label('Can Borrow')
                    ->query(fn($query) => $query->whereRaw('fund_account_balance > outstanding_loans')),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),

                Actions\Action::make('suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        Forms\Components\Textarea::make('reason')
                            ->required()
                            ->label('Suspension Reason'),
                    ])
                    ->action(function (User $record, array $data) {
                        $record->suspend($data['reason']);
                    })
                    ->visible(fn(User $record) => $record->status === 'active'),

                Actions\Action::make('activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn(User $record) => $record->activate())
                    ->visible(fn(User $record) => $record->status !== 'active'),
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
                Components\Section::make('User Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('user_code')
                            ->label('User ID'),
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('email')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('phone'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->colors([
                                'success' => 'active',
                                'warning' => 'inactive',
                                'danger' => 'suspended',
                            ]),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),
                    ])
                    ->columns(2),

                Components\Section::make('Account Balances')
                    ->schema([
                        Infolists\Components\TextEntry::make('bank_account_balance')
                            ->label('Bank Account')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('fund_account_balance')
                            ->label('Fund Account')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('outstanding_loans')
                            ->label('Outstanding Loans')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('available_to_borrow')
                            ->label('Available to Borrow')
                            ->state(fn ($record) => $record ? max(0, $record->fund_account_balance - $record->outstanding_loans) : 0)
                            ->money('USD')
                            ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                    ])
                    ->columns(2),

                Components\Section::make('Activity Summary')
                    ->schema([
                        Infolists\Components\TextEntry::make('total_contributions')
                            ->label('Total Contributions')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('total_loan_repayments')
                            ->label('Total Loan Repayments')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('activeLoans')
                            ->label('Active Loans')
                            ->state(fn($record) => $record->activeLoans()->count()),
                        Infolists\Components\TextEntry::make('transactions')
                            ->label('Total Transactions')
                            ->state(fn($record) => $record->transactions()->count()),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\UserResource\RelationManagers\TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::active()->count();
    }
}
