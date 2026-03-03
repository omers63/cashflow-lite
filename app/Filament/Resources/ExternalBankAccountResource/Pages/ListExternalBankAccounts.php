<?php

namespace App\Filament\Resources\ExternalBankAccountResource\Pages;

use App\Filament\Resources\ExternalBankAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExternalBankAccounts extends ListRecords
{
    protected static string $resource = ExternalBankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('')
                ->icon('heroicon-o-plus-circle')
                ->tooltip('Create'),
        ];
    }
}
