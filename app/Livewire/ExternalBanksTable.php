<?php

namespace App\Livewire;

use App\Filament\Pages\ImportExternalBank;
use App\Filament\Resources\ExternalBankAccountResource;
use App\Filament\Resources\ExternalBankImportBatchResource;
use App\Models\ExternalBankAccount;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Contracts\View\View;

class ExternalBanksTable extends TableComponent
{
    public function table(Table $table): Table
    {
        return $table
            ->query(ExternalBankAccount::query())
            ->columns([
                Tables\Columns\TextColumn::make('bank_name')
                    ->label('Bank')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('account_number')
                    ->label('Account Number')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('account_type')
                    ->label('Type'),

                Tables\Columns\TextColumn::make('current_balance')
                    ->label('Current Balance')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD')
                            ->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('imports_count')
                    ->label('Imports')
                    ->counts('imports'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'warning' => 'inactive',
                        'danger' => 'closed',
                    ]),
            ])
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
                    ->label('Create External Bank')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->url(ExternalBankAccountResource::getUrl('create')),
                Actions\Action::make('import_sessions')
                    ->label('Import Sessions')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->button()
                    ->color('success')
                    ->url(ExternalBankImportBatchResource::getUrl('index')),
            ])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\Action::make('view')
                        ->label('View')
                        ->tooltip('View')
                        ->icon('heroicon-o-eye')
                        ->url(fn(ExternalBankAccount $record) => ExternalBankAccountResource::getUrl('view', ['record' => $record])),

                    Actions\Action::make('import')
                        ->label('Import')
                        ->tooltip('Import Transactions')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(fn(ExternalBankAccount $record) => ImportExternalBank::getUrl() . '?bank=' . (int) $record->getKey()),

                    Actions\Action::make('recalculate')
                        ->label('Recalculate Balance')
                        ->tooltip('Recalculate Balance from imports')
                        ->icon('heroicon-o-calculator')
                        ->requiresConfirmation()
                        ->action(function (ExternalBankAccount $record): void {
                            $balance = $record->recalculateCurrentBalanceFromImports();
                            $record->refresh();

                            Notification::make()
                                ->title('Balance recalculated')
                                ->body('New balance: $' . number_format($balance, 2))
                                ->success()
                                ->send();
                        }),

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
            ->defaultSort('bank_name')
            ->paginated([10, 25, 50])
            ->striped();
    }

    public function render(): View
    {
        return view('livewire.external-banks-table');
    }
}

