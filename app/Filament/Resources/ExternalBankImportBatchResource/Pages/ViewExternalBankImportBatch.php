<?php

namespace App\Filament\Resources\ExternalBankImportBatchResource\Pages;

use App\Filament\Resources\ExternalBankImportBatchResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class ViewExternalBankImportBatch extends ViewRecord
{
    protected static string $resource = ExternalBankImportBatchResource::class;

    #[On('refreshBatchRecord')]
    public function refreshBatchRecord(?int $batchId = null): void
    {
        if ($batchId !== null && $this->record->getKey() !== $batchId) {
            return;
        }
        $this->record = $this->record->fresh() ?? $this->record;
        $this->dispatch('$refresh');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('post_all_new')
                ->label('Post All New to Master')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Post all new rows to Master Bank')
                ->modalDescription('Post all non-duplicate, not-yet-posted transactions in this session to the Master Bank Account.')
                ->action(function (): void {
                    $this->postAllNewToMaster();
                }),
            Actions\Action::make('post_all')
                ->label('Post All To Master')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Post all rows to Master Bank')
                ->modalDescription('Post all not-yet-posted transactions in this session to the Master Bank Account (including duplicates).')
                ->action(function (): void {
                    $this->postAllToMaster();
                }),
            Actions\DeleteAction::make(),
        ];
    }

    public function postAllNewToMaster(): void
    {
        $batch = $this->record;
        $imports = $batch->imports()
            ->where('is_duplicate', false)
            ->where('imported_to_master', false)
            ->get();

        $posted = 0;

        DB::transaction(function () use ($imports, $batch, &$posted): void {
            foreach ($imports as $import) {
                $import->postToMasterBank();
                $posted++;
            }

            if ($posted > 0) {
                $batch->increment('rows_posted', $posted);
            }
        });

        Notification::make()
            ->title('Post to Master complete')
            ->body($posted . ' new transaction(s) posted to Master Bank.')
            ->success()
            ->send();

        $this->record->refresh();
        $this->dispatch('$refresh');
    }

    public function postAllToMaster(): void
    {
        $batch = $this->record;
        $imports = $batch->imports()
            ->where('imported_to_master', false)
            ->get();

        $posted = 0;

        DB::transaction(function () use ($imports, $batch, &$posted): void {
            foreach ($imports as $import) {
                $import->postToMasterBank();
                $posted++;
            }

            if ($posted > 0) {
                $batch->increment('rows_posted', $posted);
            }
        });

        Notification::make()
            ->title('Post to Master complete')
            ->body($posted . ' transaction(s) posted to Master Bank.')
            ->success()
            ->send();

        $this->record->refresh();
        $this->dispatch('$refresh');
    }
}

