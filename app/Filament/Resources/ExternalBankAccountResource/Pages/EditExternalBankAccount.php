<?php

namespace App\Filament\Resources\ExternalBankAccountResource\Pages;

use App\Filament\Resources\ExternalBankAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExternalBankAccount extends EditRecord
{
    protected static string $resource = ExternalBankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
