<?php

namespace App\Filament\Resources\ExceptionResource\Pages;

use App\Filament\Resources\ExceptionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditException extends EditRecord
{
    protected static string $resource = ExceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
