<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

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
            'Transactions'          => \App\Models\Transaction::class,
            'Users'                 => \App\Models\User::class,
            'Loans'                 => \App\Models\Loan::class,
            'External Bank Accounts'=> \App\Models\ExternalBankAccount::class,
            'External Bank Imports' => \App\Models\ExternalBankImport::class,
            'Exceptions'            => \App\Models\Exception::class,
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

    public function getTotalTrashedProperty(): int
    {
        return array_sum($this->getTrashedCounts());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('purge_all')
                ->label('Purge All')
                ->icon('heroicon-o-trash')
                ->color('danger')
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
                        ->body($total . ' record(s) permanently deleted.')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * Per-table purge actions wired from the Blade view.
     */
    public function purgeModel(string $label): void
    {
        $models = static::softDeleteModels();
        if (! array_key_exists($label, $models)) {
            return;
        }
        $model = $models[$label];
        $count = DB::transaction(fn () => $model::onlyTrashed()->forceDelete());
        $this->purged = true;
        Notification::make()
            ->title("{$label} purged")
            ->body($count . ' record(s) permanently deleted.')
            ->success()
            ->send();
    }
}
