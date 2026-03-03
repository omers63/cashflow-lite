<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReconciliationResource\Pages;
use App\Models\Reconciliation;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;

class ReconciliationResource extends Resource
{
    protected static ?string $model = Reconciliation::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static string|\UnitEnum|null $navigationGroup = 'Financial Operations';
    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\DatePicker::make('reconciliation_date')
                    ->required()
                    ->default(now()),
                Forms\Components\Select::make('type')
                    ->options([
                        'daily' => 'Daily',
                        'monthly' => 'Monthly',
                        'manual' => 'Manual',
                    ])
                    ->required()
                    ->default('daily'),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'complete' => 'Complete',
                        'failed' => 'Failed',
                        'under_review' => 'Under Review',
                    ])
                    ->required()
                    ->default('pending'),
                Forms\Components\Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reconciliation_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->colors([
                        'primary' => 'daily',
                        'success' => 'monthly',
                        'secondary' => 'manual',
                    ])
                    ->badge(),
                Tables\Columns\IconColumn::make('all_passed')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\TextColumn::make('checks_passed')
                    ->label('Passed')
                    ->sortable(),
                Tables\Columns\TextColumn::make('checks_failed')
                    ->label('Failed')
                    ->sortable()
                    ->color(fn($state) => $state > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('total_variance')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'complete',
                        'danger' => 'failed',
                        'info' => 'under_review',
                    ])
                    ->badge(),
                Tables\Columns\TextColumn::make('performer.name')
                    ->label('Performed By'),
            ])
            ->defaultSort('reconciliation_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type'),
                Tables\Filters\SelectFilter::make('status'),
                Tables\Filters\Filter::make('failed')
                    ->query(fn($query) => $query->where('all_passed', false)),
            ])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make()
                        ->label('View')
                        ->tooltip('View'),
                    Actions\DeleteAction::make()
                        ->label('Delete')
                        ->tooltip('Delete'),
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
            ->emptyStateHeading('No reconciliations yet')
            ->emptyStateDescription('Run daily reconciliation to generate records here.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('Reconciliation Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('reconciliation_date')
                            ->date(),
                        Infolists\Components\TextEntry::make('type')
                            ->badge()
                            ->color(fn($state) => match ($state) {
                                'daily' => 'primary',
                                'monthly' => 'success',
                                'manual' => 'secondary',
                                default => 'secondary',
                            }),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn($state) => match ($state) {
                                'pending' => 'warning',
                                'complete' => 'success',
                                'failed' => 'danger',
                                'under_review' => 'info',
                                default => 'secondary',
                            }),
                        Infolists\Components\IconEntry::make('all_passed')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('checks_passed'),
                        Infolists\Components\TextEntry::make('checks_failed'),
                        Infolists\Components\TextEntry::make('total_variance')
                            ->money('USD'),
                    ])
                    ->columns(2),

                Components\Section::make('Check Results')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('check_results')
                            ->label('')
                            ->columnSpanFull()
                            ->state(function ($record) {
                                $results = $record->check_results;
                                if (!is_array($results)) {
                                    return [];
                                }
                                // Convert any array/nested values to strings
                                return collect($results)->mapWithKeys(function ($value, $key) {
                                    $formattedValue = $value;
                                    if (is_array($value)) {
                                        $formattedValue = json_encode($value);
                                    } elseif (is_bool($value)) {
                                        $formattedValue = $value ? 'Passed' : 'Failed';
                                    } elseif (!is_string($value) && !is_null($value)) {
                                        $formattedValue = (string) $value;
                                    }
                                    return [$key => $formattedValue];
                                })->toArray();
                            }),
                    ]),

                Components\Section::make('Additional Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('performer.name')
                            ->label('Performed By'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReconciliations::route('/'),
            'view' => Pages\ViewReconciliation::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $failed = static::getModel()::where('all_passed', false)
            ->where('status', '!=', 'resolved')
            ->count();
        return $failed > 0 ? $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
