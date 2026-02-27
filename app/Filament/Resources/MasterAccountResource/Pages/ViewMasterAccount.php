<?php

namespace App\Filament\Resources\MasterAccountResource\Pages;

use App\Filament\Resources\MasterAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewMasterAccount extends ViewRecord
{
    protected static string $resource = MasterAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    #[On('refreshMasterAccountRecord')]
    public function refreshMasterAccountRecord(): void
    {
        $this->record->refresh();
    }
}
