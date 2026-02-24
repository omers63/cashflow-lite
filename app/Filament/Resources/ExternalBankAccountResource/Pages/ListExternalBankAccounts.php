<?php

namespace App\Filament\Resources\ExternalBankAccountResource\Pages;

use App\Filament\Pages\ImportExternalBank;
use App\Filament\Resources\ExternalBankAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExternalBankAccounts extends ListRecords
{
    protected static string $resource = ExternalBankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import')
                ->label('Import Bank Transactions')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(ImportExternalBank::getUrl())
                ->color('info'),

            Actions\CreateAction::make(),
        ];
    }
}
