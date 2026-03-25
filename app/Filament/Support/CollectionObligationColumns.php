<?php

namespace App\Filament\Support;

use App\Models\LoanPayment;
use App\Models\Transaction;
use Filament\Tables;

final class CollectionObligationColumns
{
    /**
     * @return array<int, Tables\Columns\TextColumn>
     */
    public static function forTransactionRecord(): array
    {
        return [
            Tables\Columns\TextColumn::make('collection_obligation_period')
                ->label('Collection period')
                ->getStateUsing(function (Transaction $record): ?string {
                    if (! $record->qualifiesForCollectionObligationTiming()) {
                        return null;
                    }
                    $c = $record->collectionObligationClassification();

                    return $c['obligation_label'] ?? null;
                })
                ->placeholder('—')
                ->toggleable(),

            Tables\Columns\TextColumn::make('collection_due_date')
                ->label('Period due')
                ->getStateUsing(function (Transaction $record): ?string {
                    if (! $record->qualifiesForCollectionObligationTiming()) {
                        return null;
                    }
                    $c = $record->collectionObligationClassification();
                    if ($c === null) {
                        return null;
                    }

                    return $c['due_date']->format('M j, Y');
                })
                ->placeholder('—')
                ->toggleable(),

            Tables\Columns\TextColumn::make('collection_timing')
                ->label('On time / late')
                ->badge()
                ->getStateUsing(function (Transaction $record): ?string {
                    if (! $record->qualifiesForCollectionObligationTiming()) {
                        return null;
                    }
                    $c = $record->collectionObligationClassification();
                    if ($c === null) {
                        return null;
                    }

                    return $c['is_late'] ? 'Late' : 'On time';
                })
                ->color(fn (?string $state): string => match ($state) {
                    'Late' => 'danger',
                    'On time' => 'success',
                    default => 'gray',
                })
                ->placeholder('—')
                ->toggleable(),
        ];
    }

    /**
     * @return array<int, Tables\Columns\TextColumn>
     */
    public static function forLoanPaymentRecord(): array
    {
        return [
            Tables\Columns\TextColumn::make('collection_obligation_period')
                ->label('Collection period')
                ->getStateUsing(function (LoanPayment $record): ?string {
                    $c = $record->collectionObligationClassification();

                    return $c['obligation_label'] ?? null;
                })
                ->placeholder('—')
                ->toggleable(),

            Tables\Columns\TextColumn::make('collection_due_date')
                ->label('Period due')
                ->getStateUsing(function (LoanPayment $record): ?string {
                    $c = $record->collectionObligationClassification();
                    if ($c === null) {
                        return null;
                    }

                    return $c['due_date']->format('M j, Y');
                })
                ->placeholder('—')
                ->toggleable(),

            Tables\Columns\TextColumn::make('collection_timing')
                ->label('On time / late')
                ->badge()
                ->getStateUsing(function (LoanPayment $record): ?string {
                    $c = $record->collectionObligationClassification();
                    if ($c === null) {
                        return null;
                    }

                    return $c['is_late'] ? 'Late' : 'On time';
                })
                ->color(fn (?string $state): string => match ($state) {
                    'Late' => 'danger',
                    'On time' => 'success',
                    default => 'gray',
                })
                ->placeholder('—')
                ->toggleable(),
        ];
    }
}
