<?php

namespace App\Filament\Pages;

use App\Models\Reconciliation;
use App\Services\ReconciliationService;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class DailyReconciliation extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';
    protected string $view = 'filament.pages.daily-reconciliation';
    protected static string|\UnitEnum|null $navigationGroup = 'Financial Operations';
    protected static ?int $navigationSort = 10;

    public ?array $latestReconciliation = null;
    public ?array $systemTotals = null;

    public function mount(): void
    {
        $service = app(ReconciliationService::class);
        $summary = $service->getReconciliationSummary();
        $this->latestReconciliation = $summary;
        $this->systemTotals = $service->getSystemTotals();
    }

    protected function getHeaderActions(): array
    {
        $latest = $this->latestReconciliation['latest'] ?? null;

        return [
            Action::make('delete_latest')
                ->label('')
                ->tooltip('Delete Latest Reconciliation')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('Delete Latest Reconciliation')
                ->modalDescription('Are you sure you want to delete the latest reconciliation record? This cannot be undone.')
                ->action(function () {
                    $latest = $this->latestReconciliation['latest'] ?? null;
                    if ($latest instanceof Reconciliation) {
                        $latest->delete();
                        $this->mount();
                        Notification::make()
                            ->title('Reconciliation deleted')
                            ->success()
                            ->send();
                    }
                })
                ->visible(fn () => $latest instanceof Reconciliation),

            Action::make('run_reconciliation')
                ->label('')
                ->tooltip('Run Reconciliation')
                ->icon('heroicon-o-play')
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        $service = app(ReconciliationService::class);
                        $reconciliation = $service->runDailyReconciliation();

                        if ($reconciliation->all_passed) {
                            Notification::make()
                                ->title('Reconciliation Passed!')
                                ->success()
                                ->body('All 7 checks passed successfully.')
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Reconciliation Failed')
                                ->danger()
                                ->body("{$reconciliation->checks_failed} checks failed.")
                                ->send();
                        }

                        $this->mount();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
        ];
    }
}
