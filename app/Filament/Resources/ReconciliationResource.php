<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReconciliationResource\Pages;
use App\Models\Reconciliation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ReconciliationResource extends Resource
{
    protected static ?string $model = Reconciliation::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Financial Operations';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
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
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'daily',
                        'success' => 'monthly',
                        'secondary' => 'manual',
                    ]),
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
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'complete',
                        'danger' => 'failed',
                        'info' => 'under_review',
                    ]),
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
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Reconciliation Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('reconciliation_date')
                            ->date(),
                        Infolists\Components\BadgeEntry::make('type'),
                        Infolists\Components\BadgeEntry::make('status'),
                        Infolists\Components\IconEntry::make('all_passed')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('checks_passed'),
                        Infolists\Components\TextEntry::make('checks_failed'),
                        Infolists\Components\TextEntry::make('total_variance')
                            ->money('USD'),
                    ])
                    ->columns(2),
                
                Infolists\Components\Section::make('Check Results')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('check_results')
                            ->label('')
                            ->columnSpanFull(),
                    ]),
                
                Infolists\Components\Section::make('Additional Information')
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
