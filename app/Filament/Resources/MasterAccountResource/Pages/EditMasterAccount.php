<?php

namespace App\Filament\Resources\MasterAccountResource\Pages;

use App\Filament\Resources\MasterAccountResource;
use App\Models\Transaction;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMasterAccount extends EditRecord
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

                    Transaction::create([
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

                    // Refresh the balance field in the form in real-time
                    $this->record = $account->fresh();
                    $this->refreshFormData(['balance']);
                }),

            Actions\ViewAction::make()
                ->label('')
                ->tooltip('View'),
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
