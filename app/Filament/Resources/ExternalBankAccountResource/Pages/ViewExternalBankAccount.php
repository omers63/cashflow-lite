<?php

namespace App\Filament\Resources\ExternalBankAccountResource\Pages;

use App\Filament\Pages\ImportExternalBank;
use App\Filament\Resources\ExternalBankAccountResource;
use App\Models\ExternalBankImport;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewExternalBankAccount extends ViewRecord
{
    protected static string $resource = ExternalBankAccountResource::class;

    #[On('refreshExternalBankAccountRecord')]
    public function refreshExternalBankAccountRecord(?int $accountId = null): void
    {
        if ($accountId !== null && $this->record->getKey() !== $accountId) {
            return;
        }
        $this->record = $this->record->fresh() ?? $this->record;
        $this->dispatch('$refresh');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_transaction')
                ->label('New Transaction')
                ->icon('heroicon-o-plus-circle')
                ->form([
                    Forms\Components\Select::make('transaction_type')
                        ->label('Type')
                        ->options([
                            'credit' => 'Credit (Money In)',
                            'debit'  => 'Debit (Money Out)',
                        ])
                        ->required()
                        ->default('credit')
                        ->live(),

                    Forms\Components\TextInput::make('amount')
                        ->label('Amount')
                        ->numeric()
                        ->prefix('$')
                        ->required()
                        ->minValue(0.01)
                        ->step(0.01),

                    Forms\Components\DateTimePicker::make('transaction_date')
                        ->label('Transaction Date')
                        ->required()
                        ->default(now()),

                    Forms\Components\TextInput::make('external_ref_id')
                        ->label('Reference / Cheque No.')
                        ->maxLength(255),

                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(2),

                    Forms\Components\Textarea::make('notes')
                        ->label('Internal Notes')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $account = $this->record;
                    $rawAmount = (float) $data['amount'];

                    // Debits are stored as negative amounts
                    $amount = $data['transaction_type'] === 'debit' ? -$rawAmount : $rawAmount;

                    ExternalBankImport::create([
                        'external_bank_account_id' => $account->id,
                        'import_date'              => now(),
                        'transaction_date'         => $data['transaction_date'],
                        'external_ref_id'          => $data['external_ref_id'] ?? ('MAN-' . strtoupper(uniqid())),
                        'amount'                   => $amount,
                        'description'              => $data['description'] ?? null,
                        'is_duplicate'             => false,
                        'imported_to_master'       => false,
                        'notes'                    => $data['notes'] ?? null,
                        'imported_by'              => auth()->id(),
                    ]);

                    // Update the account balance to reflect the new transaction
                    $account->increment('current_balance', $amount);

                    $label = $data['transaction_type'] === 'debit' ? 'Debit' : 'Credit';
                    Notification::make()
                        ->title("{$label} transaction recorded")
                        ->body('$' . number_format($rawAmount, 2) . ' ' . strtolower($label) . ' added to ' . $account->bank_name)
                        ->success()
                        ->send();

                    // Redirect back to this page to fully refresh both the table and balance display
                    $this->redirect(
                        ExternalBankAccountResource::getUrl('view', ['record' => $account->getKey()])
                    );
                }),

            Actions\Action::make('import_bank_transactions')
                ->label('')
                ->tooltip('Import Bank Transactions')
                ->icon('heroicon-o-arrow-down-tray')
                ->link()
                ->url(ImportExternalBank::getUrl() . '?bank=' . (int) $this->record->getKey()),

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
                    $this->record = $account->fresh();
                    Notification::make()
                        ->title('Balance recalculated')
                        ->body('New balance: $' . number_format($balance, 2))
                        ->success()
                        ->send();
                    // Ensure both this page and the related imports table update immediately.
                    // 1) Re-render this Livewire component.
                    $this->dispatch('$refresh');
                    // 2) Notify the relation manager to refresh its table and owner record.
                    $this->dispatch('refreshExternalBankAccountRecord', accountId: $account->getKey());
                }),

            Actions\EditAction::make()
                ->label('')
                ->tooltip('Edit')
                ->icon('heroicon-o-pencil-square')
                ->link(),
        ];
    }
}
