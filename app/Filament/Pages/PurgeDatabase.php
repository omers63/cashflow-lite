<?php

namespace App\Filament\Pages;

use App\Models\BalanceSnapshot;
use App\Models\Exception as ExceptionModel;
use App\Models\LoanPayment;
use App\Models\Reconciliation;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\SoftDeletes;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class PurgeDatabase extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trash';
    protected static string|\UnitEnum|null $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'Purge Database';
    protected static ?int $navigationSort = 10;
    protected static ?string $title = 'Purge Database';

    protected string $view = 'filament.pages.purge-database';

    /** Re-render the page after a purge so counts update. */
    public bool $purged = false;

    /**
     * All models that use SoftDeletes, keyed by a display label.
     * @return array<string, class-string>
     */
    public static function softDeleteModels(): array
    {
        return [
            'Transactions' => \App\Models\Transaction::class,
            'Users' => \App\Models\User::class,
            'Loans' => \App\Models\Loan::class,
            'External Bank Accounts' => \App\Models\ExternalBankAccount::class,
            'External Bank Imports' => \App\Models\ExternalBankImport::class,
            'Exceptions' => ExceptionModel::class,
        ];
    }

    /**
     * Tables that can be fully cleared (all records deleted). No soft deletes.
     * @return array<string, class-string>
     */
    public static function clearableTables(): array
    {
        return [
            'Notifications (activity log)' => Activity::class,
            'Balance snapshots' => BalanceSnapshot::class,
            'Reconciliations' => Reconciliation::class,
            'Loan payments' => LoanPayment::class,
            'Exceptions' => ExceptionModel::class,
        ];
    }

    /**
     * Returns counts of soft-deleted records per model.
     * @return array<string, int>
     */
    public function getTrashedCounts(): array
    {
        $counts = [];
        foreach (static::softDeleteModels() as $label => $model) {
            $counts[$label] = $model::onlyTrashed()->count();
        }
        return $counts;
    }

    /**
     * Returns record counts for clearable tables (all records).
     * @return array<string, int>
     */
    public function getClearableCounts(): array
    {
        $counts = [];
        foreach (static::clearableTables() as $label => $model) {
            $query = $model::query();
            if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                $query = $query->withTrashed();
            }
            $counts[$label] = $query->count();
        }
        return $counts;
    }

    public function getTotalTrashedProperty(): int
    {
        return array_sum($this->getTrashedCounts());
    }

    public function getTotalClearableProperty(): int
    {
        return array_sum($this->getClearableCounts());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('purge_all_trashed')
                ->label('Purge all soft-deleted')
                ->tooltip('Permanently delete all soft-deleted records')
                ->icon('heroicon-o-trash')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Permanently delete ALL soft-deleted records?')
                ->modalDescription('This will permanently remove every soft-deleted record across all tables. This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, purge everything')
                ->action(function (): void {
                    $total = 0;
                    DB::transaction(function () use (&$total): void {
                        foreach (static::softDeleteModels() as $model) {
                            $total += $model::onlyTrashed()->forceDelete();
                        }
                    });
                    $this->purged = true;
                    Notification::make()
                        ->title('Purge complete')
                        ->body($total . ' soft-deleted record(s) permanently deleted.')
                        ->success()
                        ->send();
                }),
            Action::make('purge_all_clearable')
                ->label('Clear all (listed tables)')
                ->tooltip('Delete all records from Notifications, Balance snapshots, Reconciliations, Loan payments, Exceptions')
                ->icon('heroicon-o-bolt')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Clear ALL records from these tables?')
                ->modalDescription('This will permanently delete every record in: Notifications (activity log), Balance snapshots, Reconciliations, Loan payments, Exceptions. This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, clear everything')
                ->action(function (): void {
                    $total = 0;
                    DB::transaction(function () use (&$total): void {
                        foreach (static::clearableTables() as $model) {
                            $query = $model::query();
                            if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                                $total += $query->withTrashed()->forceDelete();
                            } else {
                                $total += $query->delete();
                            }
                        }
                    });
                    $this->purged = true;
                    Notification::make()
                        ->title('Clear complete')
                        ->body($total . ' record(s) deleted from clearable tables.')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * Per-table purge of soft-deleted records (wired from the Blade view).
     */
    public function purgeModel(string $label): void
    {
        $models = static::softDeleteModels();
        if (!array_key_exists($label, $models)) {
            return;
        }
        $model = $models[$label];
        $count = DB::transaction(fn() => $model::onlyTrashed()->forceDelete());
        $this->purged = true;
        Notification::make()
            ->title("{$label} purged")
            ->body($count . ' record(s) permanently deleted.')
            ->success()
            ->send();
    }

    /**
     * Clear all records from a single clearable table (wired from the Blade view).
     */
    public function clearTable(string $label): void
    {
        $tables = static::clearableTables();
        if (!array_key_exists($label, $tables)) {
            return;
        }
        $model = $tables[$label];
        $count = DB::transaction(function () use ($model) {
            $query = $model::query();
            if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                return $query->withTrashed()->forceDelete();
            }
            return $query->delete();
        });
        $this->purged = true;
        Notification::make()
            ->title("{$label} cleared")
            ->body($count . ' record(s) deleted.')
            ->success()
            ->send();
    }
}
