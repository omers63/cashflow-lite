<?php

namespace App\Filament\Resources\MasterAccountResource\Pages;

use App\Filament\Resources\MasterAccountResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewMasterAccount extends ViewRecord
{
    protected static string $resource = MasterAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('')
                ->tooltip('Edit')
                ->icon('heroicon-o-pencil-square')
                ->link(),
            Actions\Action::make('create_external_bank')
                ->label('')
                ->tooltip('Create External Bank')
                ->icon('heroicon-o-plus-circle')
                ->link()
                ->url(\App\Filament\Resources\ExternalBankAccountResource::getUrl('create')),
            Actions\Action::make('recalculate_balance')
                ->label('')
                ->tooltip('Recalculate Balance')
                ->icon('heroicon-o-calculator')
                ->link()
                ->requiresConfirmation()
                ->modalHeading('Recalculate balance from transactions')
                ->modalDescription('This will set the current balance to the sum of transaction effects (credits minus debits). Use when the balance is out of sync or should be zero when there are no transactions.')
                ->action(function (): void {
                    $account = $this->record;
                    $balance = $account->recalculateBalanceFromTransactions();
                    $account->refresh();
                    Notification::make()
                        ->title('Balance recalculated')
                        ->body('New balance: $' . number_format($balance, 2))
                        ->success()
                        ->send();
                }),
        ];
    }

    #[On('refreshMasterAccountRecord')]
    public function refreshMasterAccountRecord(): void
    {
        $this->record->refresh();
    }
}
