<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExternalBankAccountResource\Pages;
use App\Models\ExternalBankAccount;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ExternalBankAccountResource extends Resource
{
    protected static ?string $model = ExternalBankAccount::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';
    protected static string|\UnitEnum|null $navigationGroup = 'Financial Operations';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'External Banks';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('bank_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('account_number')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\Select::make('account_type')
                    ->options([
                        'checking' => 'Checking',
                        'savings' => 'Savings',
                    ])
                    ->default('checking')
                    ->required(),
                Forms\Components\TextInput::make('current_balance')
                    ->numeric()
                    ->prefix('$')
                    ->default(0)
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'closed' => 'Closed',
                    ])
                    ->default('active')
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bank_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('account_number')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('account_type'),
                Tables\Columns\TextColumn::make('current_balance')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD')
                            ->label('Total'),
                    ]),
                Tables\Columns\TextColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'inactive',
                        'danger' => 'closed',
                    ])
                    ->badge(),
                Tables\Columns\TextColumn::make('imports_count')
                    ->label('Imports')
                    ->counts('imports'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'closed' => 'Closed',
                    ]),
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
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\ExternalBankAccountResource\RelationManagers\ImportsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExternalBankAccounts::route('/'),
            'create' => Pages\CreateExternalBankAccount::route('/create'),
            'view' => Pages\ViewExternalBankAccount::route('/{record}'),
            'edit' => Pages\EditExternalBankAccount::route('/{record}/edit'),
        ];
    }

    /**
     * Hide this resource from the main navigation; it is now accessed via
     * the Master Accounts page (as a related table).
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
