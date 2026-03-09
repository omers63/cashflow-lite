<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExceptionResource\Pages;
use App\Models\Exception;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Schemas\Components;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ExceptionResource extends Resource
{
    protected static ?string $model = Exception::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static string|\UnitEnum|null $navigationGroup = 'System';
    protected static ?int $navigationSort = 6;
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('exception_id')
                    ->default(fn() => Exception::generateExceptionId())
                    ->disabled()
                    ->dehydrated()
                    ->required(),
                Forms\Components\Select::make('type')
                    ->options([
                        'duplicate_import' => 'Duplicate Import',
                        'balance_mismatch' => 'Balance Mismatch',
                        'negative_balance' => 'Negative Balance',
                        'loan_payment_mismatch' => 'Loan Payment Mismatch',
                        'loan_delinquency' => 'Loan Delinquency',
                        'missing_transaction' => 'Missing Transaction',
                        'fund_account_negative' => 'Fund Account Negative',
                        'other' => 'Other',
                    ])
                    ->required(),
                Forms\Components\Select::make('severity')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'critical' => 'Critical',
                    ])
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, Set $set) {
                        $slaHours = Exception::getSlaHours($state);
                        $set('sla_hours', $slaHours);
                        $set('sla_deadline', now()->addHours($slaHours));
                    }),
                Forms\Components\Textarea::make('description')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('variance_amount')
                    ->numeric()
                    ->prefix('$'),
                Forms\Components\Select::make('status')
                    ->options([
                        'open' => 'Open',
                        'under_investigation' => 'Under Investigation',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                    ])
                    ->required()
                    ->default('open'),
                Forms\Components\Select::make('assigned_to')
                    ->relationship('assignedUser', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Textarea::make('resolution_notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('exception_id')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('type')
                    ->formatStateUsing(fn($state) => str_replace('_', ' ', ucfirst($state)))
                    ->badge(),
                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'low' => 'secondary',
                        'medium' => 'warning',
                        'high', 'critical' => 'danger',
                        default => 'secondary',
                    }),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->tooltip(fn($record) => $record->description),
                Tables\Columns\TextColumn::make('variance_amount')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->colors([
                        'danger' => 'open',
                        'warning' => 'under_investigation',
                        'success' => 'resolved',
                        'secondary' => 'closed',
                    ])
                    ->badge(),
                Tables\Columns\TextColumn::make('assignedUser.name')
                    ->label('Assigned To'),
                Tables\Columns\TextColumn::make('sla_deadline')
                    ->dateTime()
                    ->sortable()
                    ->color(fn($record) => $record->sla_deadline->isPast() && $record->status !== 'resolved' ? 'danger' : null),
                Tables\Columns\IconColumn::make('sla_breached')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('success'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('severity'),
                Tables\Filters\SelectFilter::make('status'),
                Tables\Filters\SelectFilter::make('related_reconciliation_id')
                    ->relationship('relatedReconciliation', 'id', fn ($query, $get) => $query->orderBy('reconciliation_date', 'desc'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->reconciliation_date?->format('Y-m-d H:i') . ' (' . ($record->all_passed ? 'passed' : 'failed') . ')')
                    ->label('Reconciliation'),
                Tables\Filters\Filter::make('overdue')
                    ->query(fn($query) => $query->overdue()),
                Tables\Filters\Filter::make('open')
                    ->query(fn($query) => $query->open()),
            ])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make()
                        ->label('View')
                        ->tooltip('View'),
                    Actions\EditAction::make()
                        ->label('Edit')
                        ->tooltip('Edit'),
                    Actions\DeleteAction::make()
                        ->label('Delete')
                        ->tooltip('Delete'),
                ])
                    ->label('')
                    ->icon('heroicon-o-ellipsis-horizontal'),
            ])
            ->striped()
            ->emptyStateHeading('No exceptions found')
            ->emptyStateDescription('Exceptions will appear here when reconciliation or other checks detect issues.')
            ->emptyStateIcon('heroicon-o-exclamation-triangle');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('Exception Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('exception_id'),
                        Infolists\Components\TextEntry::make('type')
                            ->badge()
                            ->formatStateUsing(fn($state) => str_replace('_', ' ', ucfirst($state))),
                        Infolists\Components\TextEntry::make('severity')
                            ->badge()
                            ->color(fn($state) => match ($state) {
                                'low' => 'secondary',
                                'medium' => 'warning',
                                'high' => 'danger',
                                'critical' => 'danger',
                                default => 'secondary',
                            }),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn($state) => match ($state) {
                                'open' => 'danger',
                                'under_investigation' => 'warning',
                                'resolved' => 'success',
                                'closed' => 'secondary',
                                default => 'secondary',
                            }),
                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('variance_amount')
                            ->money('USD'),
                    ])
                    ->columns(2),

                Components\Section::make('Assignment & SLA')
                    ->schema([
                        Infolists\Components\TextEntry::make('assignedUser.name')
                            ->label('Assigned To'),
                        Infolists\Components\TextEntry::make('sla_hours')
                            ->suffix(' hours'),
                        Infolists\Components\TextEntry::make('sla_deadline')
                            ->dateTime(),
                        Infolists\Components\IconEntry::make('sla_breached')
                            ->boolean(),
                    ])
                    ->columns(2),

                Components\Section::make('Resolution')
                    ->schema([
                        Infolists\Components\TextEntry::make('resolution_notes')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('resolver.name')
                            ->label('Resolved By'),
                        Infolists\Components\TextEntry::make('resolved_at')
                            ->dateTime(),
                    ])
                    ->columns(2)
                    ->visible(fn($record) => $record->status === 'resolved'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExceptions::route('/'),
            'create' => Pages\CreateException::route('/create'),
            'view' => Pages\ViewException::route('/{record}'),
            'edit' => Pages\EditException::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::open()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $overdue = static::getModel()::overdue()->count();
        return $overdue > 0 ? 'danger' : 'warning';
    }
}
