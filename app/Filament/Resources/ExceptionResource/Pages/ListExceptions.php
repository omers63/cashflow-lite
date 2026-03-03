<?php

namespace App\Filament\Resources\ExceptionResource\Pages;

use App\Filament\Resources\ExceptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExceptions extends ListRecords
{
    protected static string $resource = ExceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('')
                ->tooltip('Create')
                ->link(),
        ];
    }
}
