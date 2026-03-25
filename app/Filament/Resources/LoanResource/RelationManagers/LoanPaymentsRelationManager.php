<?php

namespace App\Filament\Resources\LoanResource\RelationManagers;

use App\Filament\Resources\TransactionResource;
use App\Filament\Support\CollectionObligationColumns;
use App\Models\LoanPayment;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LoanPaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Repayments';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-arrow-uturn-left';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('transaction'))
            ->columns([
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Date')
                    ->date('M d, Y')
                    ->sortable(),

                ...CollectionObligationColumns::forLoanPaymentRecord(),

                Tables\Columns\TextColumn::make('payment_amount')
                    ->label('Payment')
                    ->money('USD')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('USD')),

                Tables\Columns\TextColumn::make('principal_amount')
                    ->label('Principal')
                    ->money('USD')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('USD')),

                Tables\Columns\TextColumn::make('interest_amount')
                    ->label('Interest')
                    ->money('USD')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('USD')),

                Tables\Columns\TextColumn::make('balance_after_payment')
                    ->label('Balance after')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? str_replace('_', ' ', ucfirst($state)) : ''),

                Tables\Columns\TextColumn::make('transaction.transaction_id')
                    ->label('Ledger ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('notes')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('payment_date', 'desc')
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make()
                        ->label('View transaction')
                        ->tooltip('Open this repayment in the ledger')
                        ->icon('heroicon-o-eye')
                        ->url(fn (LoanPayment $record): ?string => $record->transaction
                            ? TransactionResource::getUrl('view', ['record' => $record->transaction])
                            : null)
                        ->visible(fn (LoanPayment $record): bool => $record->transaction !== null),

                    Actions\EditAction::make()
                        ->label('Edit transaction')
                        ->tooltip('Edit ledger entry (amount, date, notes)')
                        ->icon('heroicon-o-pencil-square')
                        ->url(fn (LoanPayment $record): ?string => $record->transaction
                            ? TransactionResource::getUrl('edit', ['record' => $record->transaction])
                            : null)
                        ->visible(fn (LoanPayment $record): bool => $record->transaction !== null)
                        ->authorize(fn (LoanPayment $record): bool => $record->transaction !== null
                            && (auth()->user()?->can('update', $record->transaction) ?? false)),

                    Actions\Action::make('edit_payment_notes')
                        ->label('Edit repayment notes')
                        ->tooltip('Notes stored on this repayment record only')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Forms\Components\Textarea::make('notes')
                                ->label('Notes')
                                ->rows(4)
                                ->columnSpanFull(),
                        ])
                        ->fillForm(fn (LoanPayment $record): array => [
                            'notes' => $record->notes,
                        ])
                        ->action(function (LoanPayment $record, array $data): void {
                            $record->update([
                                'notes' => $data['notes'] !== '' ? $data['notes'] : null,
                            ]);
                            Notification::make()
                                ->title('Repayment notes saved')
                                ->success()
                                ->send();
                        }),
                ])
                    ->label('')
                    ->icon('heroicon-o-ellipsis-horizontal')
                    ->tooltip('Actions'),
            ])
            ->emptyStateHeading('No repayments yet')
            ->emptyStateDescription('Posted repayments for this loan will appear here with principal and interest breakdown.')
            ->emptyStateIcon('heroicon-o-arrow-uturn-left');
    }
}
