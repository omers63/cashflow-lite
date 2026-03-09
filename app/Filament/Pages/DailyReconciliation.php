<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ExceptionResource;
use App\Filament\Resources\ReconciliationResource;
use App\Models\Exception as ExceptionModel;
use App\Models\Reconciliation;
use App\Services\ReconciliationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class DailyReconciliation extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Reconciliation';
    protected static ?string $title = 'Reconciliation';
    protected string $view = 'filament.pages.daily-reconciliation';
    protected static string|\UnitEnum|null $navigationGroup = 'Financial Operations';
    protected static ?int $navigationSort = 3;

    public ?array $latestReconciliation = null;
    public ?array $systemTotals = null;
    public array $recentReconciliations = [];
    public array $openExceptions = [];

    public function mount(): void
    {
        $service = app(ReconciliationService::class);
        $this->latestReconciliation = $service->getReconciliationSummary();
        $this->systemTotals = $service->getSystemTotals();
        $this->recentReconciliations = $this->loadRecentReconciliations();
        $this->openExceptions = $this->loadOpenExceptions();
    }

    private function loadRecentReconciliations(): array
    {
        return Reconciliation::orderBy('reconciliation_date', 'desc')
            ->limit(10)
            ->get()
            ->map(fn(Reconciliation $r) => [
                'id' => $r->id,
                'reconciliation_date' => $r->reconciliation_date->format('M d, Y'),
                'type' => $r->type,
                'all_passed' => (bool) $r->all_passed,
                'checks_passed' => (int) $r->checks_passed,
                'checks_failed' => (int) $r->checks_failed,
                'total_variance' => (float) $r->total_variance,
                'view_url' => ReconciliationResource::getUrl('view', ['record' => $r->id]),
                'exceptions_url' => ExceptionResource::getUrl('index')
                    . '?tableFilters[related_reconciliation_id]=' . $r->id,
            ])
            ->all();
    }

    private function loadOpenExceptions(): array
    {
        return ExceptionModel::with('relatedReconciliation')
            ->open()
            ->orderBy('sla_deadline')
            ->limit(10)
            ->get()
            ->map(fn(ExceptionModel $e) => [
                'id' => $e->id,
                'exception_id' => $e->exception_id,
                'type' => $e->type,
                'severity' => $e->severity,
                'status' => $e->status,
                'related_date' => $e->relatedReconciliation?->reconciliation_date?->format('M d, Y'),
                'related_type' => $e->relatedReconciliation?->type,
                'sla_deadline' => $e->sla_deadline?->format('M d, Y H:i'),
                'sla_breached' => (bool) $e->sla_breached,
                'view_url' => ExceptionResource::getUrl('view', ['record' => $e->id]),
            ])
            ->all();
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
                        Notification::make()->title('Reconciliation deleted')->success()->send();
                    }
                })
                ->visible(fn() => $latest instanceof Reconciliation),

            Action::make('run_reconciliation')
                ->label('Run daily')
                ->tooltip('Run daily reconciliation')
                ->icon('heroicon-o-play')
                ->requiresConfirmation()
                ->modalHeading('Run Daily Reconciliation')
                ->modalDescription('Run all reconciliation checks (E1–L2) for today.')
                ->action(function () {
                    try {
                        $service = app(ReconciliationService::class);
                        $reconciliation = $service->runDailyReconciliation();
                        $total = $reconciliation->checks_passed + $reconciliation->checks_failed;
                        if ($reconciliation->all_passed) {
                            Notification::make()->title('Reconciliation Passed!')->success()->body("All {$total} checks passed.")->send();
                        } else {
                            Notification::make()->title('Reconciliation Failed')->danger()->body("{$reconciliation->checks_failed} checks failed.")->send();
                        }
                        $this->mount();
                    } catch (\Exception $e) {
                        Notification::make()->title('Error')->danger()->body($e->getMessage())->send();
                    }
                }),

            Action::make('run_monthly_reconciliation')
                ->label('Run monthly')
                ->tooltip('Run monthly reconciliation (previous month)')
                ->icon('heroicon-o-calendar')
                ->requiresConfirmation()
                ->modalHeading('Run Monthly Reconciliation')
                ->modalDescription('Run all checks for the previous month. This will create a monthly reconciliation record.')
                ->action(function () {
                    try {
                        $service = app(ReconciliationService::class);
                        $reconciliation = $service->runMonthlyReconciliation(null);
                        $total = $reconciliation->checks_passed + $reconciliation->checks_failed;
                        if ($reconciliation->all_passed) {
                            Notification::make()->title('Monthly reconciliation passed')->success()
                                ->body("All {$total} checks passed for {$reconciliation->reconciliation_date->format('F Y')}.")
                                ->send();
                        } else {
                            Notification::make()->title('Monthly reconciliation failed')->danger()
                                ->body("{$reconciliation->checks_failed} checks failed.")
                                ->send();
                        }
                        $this->mount();
                    } catch (\Exception $e) {
                        Notification::make()->title('Error')->danger()->body($e->getMessage())->send();
                    }
                }),

            Action::make('create_balance_snapshot')
                ->label('Create snapshot')
                ->tooltip('Create monthly balance snapshot (previous month)')
                ->icon('heroicon-o-document-duplicate')
                ->requiresConfirmation()
                ->modalHeading('Create Balance Snapshot')
                ->modalDescription('Store current system totals as a monthly snapshot for the previous month. Use for drift detection.')
                ->action(function () {
                    try {
                        $service = app(ReconciliationService::class);
                        $snapshot = $service->createMonthlyBalanceSnapshot(null, null);
                        Notification::make()->title('Snapshot created')->success()
                            ->body('Balance snapshot saved for ' . $snapshot->snapshot_date->format('F Y') . '.')
                            ->send();
                        $this->mount();
                    } catch (\Exception $e) {
                        Notification::make()->title('Error')->danger()->body($e->getMessage())->send();
                    }
                }),
        ];
    }
}
