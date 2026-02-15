<?php

namespace App\Filament\Resources/ExceptionResource\Pages;

use App\Filament\Resources\ExceptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewException extends ViewRecord
{
    protected static string $resource = ExceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
