<?php

namespace App\Filament\Resources\ExternalBankAccountResource\RelationManagers;

use App\Models\ExternalBankImport;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ImportsRelationManager extends RelationManager
{
    protected static string $relationship = 'imports';

    protected static ?string $title = 'Transactions';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-arrow-path';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Transaction Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('external_ref_id')
                    ->label('Reference')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('description')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->description),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD')
                            ->label('Total'),
                    ]),

                Tables\Columns\IconColumn::make('is_duplicate')
                    ->label('Duplicate')
                    ->boolean(),

                Tables\Columns\IconColumn::make('imported_to_master')
                    ->label('Imported to Master')
                    ->boolean(),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_duplicate')
                    ->label('Duplicates'),
                Tables\Filters\TernaryFilter::make('imported_to_master')
                    ->label('Imported to Master'),
            ])
            ->recordActions([
                Actions\Action::make('import_to_master')
                    ->label('Import to Master')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Import to Master Bank')
                    ->modalDescription(fn (ExternalBankImport $record) => "Post this transaction (\${$record->amount}) to the Master Bank Account?")
                    ->action(function (ExternalBankImport $record): void {
                        DB::transaction(function () use ($record): void {
                            $record->postToMasterBank();
                        });
                        $owner = $this->getOwnerRecord();
                        $owner->refresh();
                        $this->dispatch('refreshExternalBankAccountRecord', accountId: $owner->getKey());
                        Notification::make()
                            ->title('Imported to Master')
                            ->body("Transaction of \${$record->amount} posted to Master Bank.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (ExternalBankImport $record) => ! $record->imported_to_master && ! $record->is_duplicate),
            ])
            ->selectable(true)
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('import_to_master_bulk')
                        ->label('Import to Master')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Import selected to Master Bank')
                        ->modalDescription('Post selected transactions to the Master Bank Account? Already imported and duplicate rows will be skipped.')
                        ->action(function (Collection $records): void {
                            $owner = $this->getOwnerRecord();
                            $imported = 0;
                            $skipped = 0;
                            DB::transaction(function () use ($records, &$imported, &$skipped): void {
                                foreach ($records as $record) {
                                    if ($record->imported_to_master || $record->is_duplicate) {
                                        $skipped++;
                                        continue;
                                    }
                                    $record->postToMasterBank();
                                    $imported++;
                                }
                            });
                            $owner->refresh();
                            $this->dispatch('refreshExternalBankAccountRecord', accountId: $owner->getKey());
                            Notification::make()
                                ->title('Import to Master complete')
                                ->body($imported . ' transaction(s) imported.' . ($skipped > 0 ? " {$skipped} skipped." : ''))
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Actions\DeleteBulkAction::make()
                        ->label('Delete Transactions')
                        ->authorize(fn () => true)
                        ->after(function (): void {
                            $owner = $this->getOwnerRecord();
                            $owner->refresh();
                            $this->dispatch('refreshExternalBankAccountRecord', accountId: $owner->getKey());
                        }),
                ]),
            ])
            ->paginated([10, 25, 50]);
    }
}

