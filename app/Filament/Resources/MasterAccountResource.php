<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MasterAccountResource\Pages;
use App\Models\MasterAccount;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class MasterAccountResource extends Resource
{
    protected static ?string $model = MasterAccount::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static string|\UnitEnum|null $navigationGroup = 'Financial Operations';
    protected static ?string $navigationLabel = 'Master Accounts';
    protected static ?int $navigationSort = 0;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $modelLabel = 'Master Account';
    protected static ?string $pluralModelLabel = 'Master Accounts';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('Account Details')
                    ->schema([
                        Forms\Components\TextInput::make('account_type')
                            ->label('Account Type')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn(?string $state) => match ($state) {
                                'master_bank' => 'Master Bank Account',
                                'master_fund' => 'Master Fund Account',
                                default => $state,
                            }),

                        Forms\Components\TextInput::make('balance')
                            ->label('Current Balance')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->step(0.01)
                            ->helperText('Aggregated balance. Update only for corrections or opening balance adjustments.'),

                        Forms\Components\TextInput::make('opening_balance')
                            ->label('Opening Balance')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->step(0.01)
                            ->helperText('Balance at start of period.'),

                        Forms\Components\DatePicker::make('balance_date')
                            ->label('Balance Date')
                            ->required()
                            ->native(false)
                            ->helperText('Date as of which balances are recorded.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('account_type')
                    ->label('Account')
                    ->formatStateUsing(fn(?string $state) => match ($state) {
                        'master_bank' => 'Master Bank Account',
                        'master_fund' => 'Master Fund Account',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn(?string $state) => match ($state) {
                        'master_bank' => 'primary',
                        'master_fund' => 'success',
                        default => 'secondary',
                    }),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Current Balance')
                    ->money('USD')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('opening_balance')
                    ->label('Opening Balance')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance_date')
                    ->label('Balance Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->defaultSort('account_type')
            ->striped();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('Account Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('account_type')
                            ->label('Account Type')
                            ->formatStateUsing(fn(?string $state) => match ($state) {
                                'master_bank' => 'Master Bank Account',
                                'master_fund' => 'Master Fund Account',
                                default => $state,
                            })
                            ->badge()
                            ->color(fn(?string $state) => match ($state) {
                                'master_bank' => 'primary',
                                'master_fund' => 'success',
                                default => 'secondary',
                            }),
                        Infolists\Components\TextEntry::make('balance')
                            ->label('Current Balance')
                            ->money('USD')
                            ->weight('bold')
                            ->size('lg'),
                        Infolists\Components\TextEntry::make('opening_balance')
                            ->label('Opening Balance')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('balance_date')
                            ->label('Balance Date')
                            ->date(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Components\Section::make('Projected balance (current month)')
                    ->description('If contributions and loan repayments for this month are run and all pending loans in the queue are disbursed. See Monthly Collections for period selector and loan queue.')
                    ->schema([
                        Infolists\Components\TextEntry::make('projected_balance_display')
                            ->label('Projected Master Fund')
                            ->state(function (MasterAccount $record): string {
                                if ($record->account_type !== 'master_fund') {
                                    return '—';
                                }
                                $service = app(\App\Services\MasterFundProjectionService::class);
                                $year = (int) now()->format('Y');
                                $month = (int) now()->format('n');
                                $p = $service->getProjection($year, $month);
                                return '$' . number_format($p['projected_balance'], 2)
                                    . ' (current: $' . number_format($p['current_balance'], 2)
                                    . ', +contrib/repay: $' . number_format($p['projected_contributions'] + $p['projected_repayments'], 2)
                                    . ', −queue: $' . number_format($p['pending_disbursements'], 2)
                                    . ', ' . $p['loan_queue_count'] . ' in queue)';
                            })
                            ->weight('bold')
                            ->visible(fn (MasterAccount $record): bool => $record->account_type === 'master_fund'),
                        Infolists\Components\TextEntry::make('monthly_collections_link')
                            ->label('')
                            ->state(fn (MasterAccount $record): string => $record->account_type === 'master_fund' ? 'View Monthly Collections' : '')
                            ->url(fn (MasterAccount $record): ?string => $record->account_type === 'master_fund' ? url('/admin/monthly-collections') : null)
                            ->visible(fn (MasterAccount $record): bool => $record->account_type === 'master_fund')
                            ->openUrlInNewTab(false),
                    ])
                    ->visible(fn (MasterAccount $record): bool => $record->account_type === 'master_fund')
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\MasterAccountResource\RelationManagers\TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMasterAccounts::route('/'),
            'view' => Pages\ViewMasterAccount::route('/{record}'),
            'edit' => Pages\EditMasterAccount::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
