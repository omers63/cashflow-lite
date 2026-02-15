<?php

namespace App\Filament\Widgets;

use App\Services\ReconciliationService;
use Filament\Widgets\Widget;

class ReconciliationStatus extends Widget
{
    protected static string $view = 'filament.widgets.reconciliation-status';
    protected static ?int $sort = 1;

    protected function getViewData(): array
    {
        $service = app(ReconciliationService::class);
        return $service->getReconciliationSummary();
    }
}
