<?php

namespace App\Filament\Resources\MasterAccountResource\RelationManagers;

use App\Filament\Resources\ExternalBankAccountResource;
use App\Models\ExternalBankAccount;
use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ExternalBankAccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'externalBankAccounts';

    protected static ?string $title = 'External Banks';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-building-library';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bank_name')
                    ->label('Bank')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('account_number')
                    ->label('Account Number')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('account_type')
                    ->label('Type'),

                TextColumn::make('current_balance')
                    ->label('Current Balance')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        Sum::make()
                            ->money('USD')
                            ->label('Total'),
                    ]),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'warning' => 'inactive',
                        'danger' => 'closed',
                    ]),
            ])
            ->defaultSort('bank_name')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'closed' => 'Closed',
                    ]),
            ])
            ->headerActions([
                Actions\Action::make('create_external_bank')
                    ->label('')
                    ->tooltip('Create External Bank')
                    ->icon('heroicon-o-plus-circle')
                    ->link()
                    ->url(ExternalBankAccountResource::getUrl('create')),
            ])
            ->recordActions([
                Actions\ViewAction::make()
                    ->label('')
                    ->tooltip('View')
                    ->url(fn(ExternalBankAccount $record) => ExternalBankAccountResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated([10, 25, 50]);
    }

    /**
     * Use a global query for all external bank accounts instead of a real Eloquent relation.
     */
    public function getTableQuery(): Builder|Relation|null
    {
        return ExternalBankAccount::query();
    }
}

