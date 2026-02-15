<?php

namespace App\Filament\Resources\ExternalBankAccountResource\Pages;

use App\Filament\Resources\ExternalBankAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewExternalBankAccount extends ViewRecord
{
    protected static string $resource = ExternalBankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
