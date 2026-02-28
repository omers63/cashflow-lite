<?php

namespace App\Filament\Resources\MasterAccountResource\Pages;

use App\Filament\Resources\MasterAccountResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMasterAccount extends EditRecord
{
    protected static string $resource = MasterAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Master account updated')
            ->body('Balance and opening balance have been saved.')
            ->success();
    }
}
