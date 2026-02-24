<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

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

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
