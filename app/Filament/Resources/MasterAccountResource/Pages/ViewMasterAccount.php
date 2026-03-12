<?php

namespace App\Filament\Resources\MasterAccountResource\Pages;

use App\Filament\Resources\MasterAccountResource;
use App\Models\Transaction;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewMasterAccount extends ViewRecord
{
    protected static string $resource = MasterAccountResource::class;

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

                    Forms\Components\TextInput::make('reference')
                        ->label('Reference')
                        ->maxLength(255),

                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $account = $this->record;
                    $rawAmount = (float) $data['amount'];
                    $isDebit = $data['transaction_type'] === 'debit';
                    $signedAmount = $isDebit ? -$rawAmount : $rawAmount;

                    $transaction = Transaction::create([
                        'transaction_id'   => Transaction::generateTransactionId('ADJ'),
                        'transaction_date' => $data['transaction_date'],
                        'type'             => 'adjustment',
                        'debit_or_credit'  => $isDebit ? 'debit' : 'credit',
                        'target_account'   => $account->account_type,
                        'amount'           => $signedAmount,
                        'reference'        => $data['reference'] ?? null,
                        'status'           => 'complete',
                        'notes'            => $data['notes'] ?? null,
                        'created_by'       => auth()->id(),
                    ]);

                    $account->increment('balance', $signedAmount);

                    $label = $isDebit ? 'Debit' : 'Credit';
                    Notification::make()
                        ->title("{$label} transaction recorded")
                        ->body('$' . number_format($rawAmount, 2) . ' ' . strtolower($label) . ' recorded on ' . $account->account_type)
                        ->success()
                        ->send();

                    // Refresh the record and update the infolist + transaction table in real-time
                    $this->record = $account->fresh();
                    $this->dispatch('$refresh');
                    $this->dispatch('refreshMasterAccountRecord');
                }),

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
