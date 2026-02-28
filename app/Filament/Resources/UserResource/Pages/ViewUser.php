<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function getRecord(): Model
    {
        $record = $this->getBaseRecord();
        $fresh = $record->fresh();
        if ($fresh) {
            $this->record = $fresh;
        }

        return $this->record;
    }

    #[On('refreshUserRecord')]
    public function refreshUserRecord(?int $userId = null): void
    {
        if ($userId !== null && $this->record->getKey() !== $userId) {
            return;
        }
        $fresh = $this->record->fresh();
        if ($fresh) {
            $this->record = $fresh;
        } else {
            $this->record->refresh();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
