<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BalanceSnapshotResource\Pages;
use App\Models\BalanceSnapshot;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BalanceSnapshotResource extends Resource
{
    protected static ?string $model = BalanceSnapshot::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationLabel = 'Balance Snapshots';
    protected static string|\UnitEnum|null $navigationGroup = 'Financial Operations';
    protected static ?int $navigationSort = 6;
    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('snapshot_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('period')
                    ->badge(),
                Tables\Columns\TextColumn::make('master_bank')
                    ->money('USD'),
                Tables\Columns\TextColumn::make('master_fund')
                    ->money('USD'),
                Tables\Columns\TextColumn::make('member_banks_total')
                    ->money('USD')
                    ->label('Member banks'),
                Tables\Columns\TextColumn::make('member_funds_total')
                    ->money('USD')
                    ->label('Member funds'),
                Tables\Columns\TextColumn::make('outstanding_loans_total')
                    ->money('USD')
                    ->label('Outstanding loans'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('snapshot_date', 'desc')
            ->striped()
            ->emptyStateHeading('No balance snapshots')
            ->emptyStateDescription('Create a snapshot from Daily Reconciliation (Create snapshot) or run: php artisan snapshots:monthly');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBalanceSnapshots::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
