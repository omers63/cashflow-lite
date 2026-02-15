<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExternalBankAccountResource\Pages;
use App\Models\ExternalBankAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExternalBankAccountResource extends Resource
{
    protected static ?string $model = ExternalBankAccount::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'External Banks';

    public static function form(Form $form): Form
    {
        return $form
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
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'inactive',
                        'danger' => 'closed',
                    ]),
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
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
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
}
