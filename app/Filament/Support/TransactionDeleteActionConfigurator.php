<?php

namespace App\Filament\Support;

use App\Models\Transaction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Throwable;

final class TransactionDeleteActionConfigurator
{
    public static function forceReversalToggle(): Forms\Components\Toggle
    {
        return Forms\Components\Toggle::make('force_balance_reversal')
            ->label('Force balance reversal')
            ->helperText(
                'When deleting completed transactions that credited a member bank account, allow debiting '.
                'even if their current bank balance is too low (balance may go negative). Use only when you accept the accounting risk.'
            )
            ->default(false);
    }

    public static function configureBulkDelete(DeleteBulkAction $action): DeleteBulkAction
    {
        return $action
            ->schema([self::forceReversalToggle()])
            ->using(function (
                DeleteBulkAction $action,
                EloquentCollection|Collection|LazyCollection $records,
            ): void {
                $force = (bool) ($action->getData()['force_balance_reversal'] ?? false);
                Transaction::withForcedReverseBalanceOnDelete($force, function () use ($action, $records): void {
                    self::runFilamentBulkDeleteProcess($action, $records);
                });
            });
    }

    public static function configureRecordDelete(DeleteAction $action): DeleteAction
    {
        return $action
            ->schema([self::forceReversalToggle()])
            ->using(function (DeleteAction $action, Model $record): bool {
                $force = (bool) ($action->getData()['force_balance_reversal'] ?? false);

                return (bool) Transaction::withForcedReverseBalanceOnDelete($force, fn (): bool => (bool) $record->delete());
            });
    }

    /**
     * Mirrors Filament's {@see DeleteBulkAction} processing so notifications and authorization stay consistent.
     *
     * @param  EloquentCollection<int, Model>|Collection<int, Model>|LazyCollection<int, Model>  $records
     */
    private static function runFilamentBulkDeleteProcess(
        DeleteBulkAction $action,
        EloquentCollection|Collection|LazyCollection $records,
    ): void {
        if (! $action->shouldFetchSelectedRecords()) {
            try {
                $action->reportBulkProcessingSuccessfulRecordsCount(
                    $action->getSelectedRecordsQuery()->delete(),
                );
            } catch (Throwable $exception) {
                $action->reportCompleteBulkProcessingFailure();

                report($exception);
            }

            return;
        }

        $isFirstException = true;

        $records->each(static function (Model $record) use ($action, &$isFirstException): void {
            try {
                $record->delete() || $action->reportBulkProcessingFailure();
            } catch (Throwable $exception) {
                $action->reportBulkProcessingFailure();

                if ($isFirstException) {
                    report($exception);

                    $isFirstException = false;
                }
            }
        });
    }
}
