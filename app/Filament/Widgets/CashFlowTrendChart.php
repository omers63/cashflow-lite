<?php

namespace App\Filament\Widgets;

use App\Models\BalanceSnapshot;
use Filament\Widgets\ChartWidget;

class CashFlowTrendChart extends ChartWidget
{
    protected ?string $heading = 'Cash Flow Trend';
    protected ?string $description = 'Master bank & fund balances over time';
    protected static ?int $sort = 5;
    protected int|string|array $columnSpan = 'full';
    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $snapshots = BalanceSnapshot::query()
            ->orderBy('snapshot_date')
            ->get()
            ->groupBy(fn ($s) => $s->snapshot_date->format('M Y'))
            ->map(fn ($group) => $group->last());

        // Take last 12
        $snapshots = $snapshots->slice(-12);

        if ($snapshots->isEmpty()) {
            return [
                'datasets' => [],
                'labels' => ['No snapshots yet'],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Master Bank',
                    'data' => $snapshots->pluck('master_bank')->map(fn ($v) => (float) $v)->values()->toArray(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Master Fund',
                    'data' => $snapshots->pluck('master_fund')->map(fn ($v) => (float) $v)->values()->toArray(),
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Outstanding Loans',
                    'data' => $snapshots->pluck('outstanding_loans_total')->map(fn ($v) => (float) $v)->values()->toArray(),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => false,
                    'tension' => 0.3,
                    'borderDash' => [5, 5],
                ],
            ],
            'labels' => $snapshots->keys()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
