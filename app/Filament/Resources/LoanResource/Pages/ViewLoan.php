<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Filament\Resources\LoanResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewLoan extends ViewRecord
{
    protected static string $resource = LoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_schedule')
                ->label('View Schedule')
                ->icon('heroicon-o-calendar')
                ->modalHeading('Amortization Schedule')
                ->modalContent(fn () => view('filament.modals.amortization-schedule', [
                    'schedule' => $this->record->generateAmortizationSchedule(),
                ]))
                ->modalSubmitAction(false),
            Actions\EditAction::make()
                ->label('')
                ->tooltip('Edit')
                ->icon('heroicon-o-pencil-square')
                ->link(),
        ];
    }
}
