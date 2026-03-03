<?php

namespace App\Filament\Resources\ExternalBankAccountResource\Pages;

use App\Filament\Pages\ImportExternalBank;
use App\Filament\Resources\ExternalBankAccountResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\On;

class EditExternalBankAccount extends EditRecord
{
    protected static string $resource = ExternalBankAccountResource::class;

    #[On('refreshExternalBankAccountRecord')]
    public function refreshExternalBankAccountRecord(?int $accountId = null): void
    {
        if ($accountId !== null && $this->record->getKey() !== $accountId) {
            return;
        }
        $fresh = $this->record->fresh();
        if ($fresh) {
            $this->record = $fresh;
        } else {
            $this->record->refresh();
        }
        $this->refreshFormData(['current_balance']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import_bank_transactions')
                ->label('')
                ->tooltip('Import Bank Transactions')
                ->icon('heroicon-o-arrow-down-tray')
                ->link()
                ->url(ImportExternalBank::getUrl() . '?bank=' . (int) $this->record->getKey())
                ,

            Actions\Action::make('recalculate_balance')
                ->label('')
                ->tooltip('Recalculate Balance')
                ->icon('heroicon-o-calculator')
                ->link()
                ->requiresConfirmation()
                ->modalHeading('Recalculate current balance from transactions')
                ->modalDescription('This will set Current Balance to the sum of all transaction amounts for this account. Use when the balance is out of sync or should be zero when there are no transactions.')
                ->action(function (): void {
                    $account = $this->record;
                    $balance = $account->recalculateCurrentBalanceFromImports();
                    $account->refresh();
                    $this->refreshFormData(['current_balance']);
                    Notification::make()
                        ->title('Balance recalculated')
                        ->body('New balance: $' . number_format($balance, 2))
                        ->success()
                        ->send();
                }),
            Actions\ViewAction::make()
                ->label('')
                ->tooltip('View'),
            Actions\DeleteAction::make()
                ->label('')
                ->tooltip('Delete'),
        ];
    }
}
