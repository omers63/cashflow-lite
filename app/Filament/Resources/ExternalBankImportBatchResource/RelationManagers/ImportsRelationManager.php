<?php

namespace App\Filament\Resources\ExternalBankImportBatchResource\RelationManagers;

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

    protected static ?string $title = 'Imported Rows';

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
                    ->color(fn ($record) => $record->amount >= 0 ? 'success' : 'danger'),

                Tables\Columns\IconColumn::make('is_duplicate')
                    ->label('Duplicate')
                    ->boolean(),

                Tables\Columns\IconColumn::make('imported_to_master')
                    ->label('Posted to Master')
                    ->boolean(),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\Action::make('post_to_master')
                        ->label('Post to Master')
                        ->tooltip('Post this row to Master Bank')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->requiresConfirmation()
                        ->modalHeading('Post to Master Bank')
                        ->modalDescription(fn (ExternalBankImport $record) => "Post this transaction (\${$record->amount}) to the Master Bank Account?")
                        ->action(function (ExternalBankImport $record): void {
                            DB::transaction(function () use ($record): void {
                                $record->postToMasterBank();
                                $batch = $record->externalBankImportBatch;
                                if ($batch) {
                                    $batch->increment('rows_posted');
                                }
                            });

                            Notification::make()
                                ->title('Posted to Master Bank')
                                ->body('Transaction posted to Master Bank.')
                                ->success()
                                ->send();

                            $this->dispatch('refreshBatchRecord', batchId: $record->externalBankImportBatch?->getKey());
                        })
                        ->visible(fn (ExternalBankImport $record) => ! $record->imported_to_master),
                ])
                    ->label('')
                    ->icon('heroicon-o-ellipsis-horizontal'),
            ])
            ->selectable()
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('post_selected')
                        ->label('Post Selected to Master')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Post selected to Master Bank')
                        ->modalDescription('Post all selected not-yet-posted rows to the Master Bank Account (including duplicates)?')
                        ->action(function (Collection $records): void {
                            $posted = 0;
                            $skipped = 0;

                            DB::transaction(function () use ($records, &$posted, &$skipped): void {
                                foreach ($records as $record) {
                                    if ($record->imported_to_master) {
                                        $skipped++;
                                        continue;
                                    }
                                    $record->postToMasterBank();
                                    if ($record->externalBankImportBatch) {
                                        $record->externalBankImportBatch->increment('rows_posted');
                                    }
                                    $posted++;
                                }
                            });

                            Notification::make()
                                ->title('Post to Master complete')
                                ->body($posted . ' transaction(s) posted.' . ($skipped > 0 ? " {$skipped} skipped." : ''))
                                ->success()
                                ->send();

                            $batch = $records->first()?->externalBankImportBatch;
                            if ($batch) {
                                $this->dispatch('refreshBatchRecord', batchId: $batch->getKey());
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->paginated([10, 25, 50]);
    }
}

