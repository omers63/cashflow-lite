<?php

namespace App\Filament\Widgets;

use App\Services\ReconciliationService;
use Filament\Widgets\Widget;

class ReconciliationStatus extends Widget
{
    protected string $view = 'filament.widgets.reconciliation-status';
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';

    public function getLatestProperty()
    {
        $service = app(ReconciliationService::class);
        $summary = $service->getReconciliationSummary();
        return $summary['latest'];
    }

    public function getMonthPassRateProperty()
    {
        $service = app(ReconciliationService::class);
        $summary = $service->getReconciliationSummary();
        return $summary['month_pass_rate'];
    }

    protected function getViewData(): array
    {
        $service = app(ReconciliationService::class);
        return $service->getReconciliationSummary();
    }
}
