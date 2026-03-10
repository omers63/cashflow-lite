<?php

namespace App\Filament\Widgets;

use App\Services\MonthlyCollectionsService;
use Carbon\Carbon;
use Filament\Widgets\Widget;

class CollectionsProgressGauge extends Widget
{
    protected static ?int $sort = 6;
    protected int|string|array $columnSpan = 'full';
    protected string $view = 'filament.widgets.collections-progress-gauge';

    public function getData(): array
    {
        $now = Carbon::now();
        $periodMonth = (int) $now->copy()->subMonth()->format('n');
        $periodYear  = (int) $now->copy()->subMonth()->format('Y');
        $periodLabel = Carbon::create($periodYear, $periodMonth, 1)->format('F Y');

        $service = app(MonthlyCollectionsService::class);
        $unrealized = $service->getUnrealizedMembers($periodYear, $periodMonth);

        $totalExpected = 0;
        $totalRealized = 0;
        $totalShortfall = 0;
        $membersWithShortfall = 0;

        foreach ($unrealized as $row) {
            $expected = ($row['expected_contribution'] ?? 0) + ($row['expected_repayment'] ?? 0);
            $realized = ($row['realized_contribution'] ?? 0) + ($row['realized_repayment'] ?? 0);
            $shortfall = $row['shortfall'] ?? 0;

            $totalExpected += $expected;
            $totalRealized += $realized;
            $totalShortfall += $shortfall;

            if ($shortfall > 0) {
                $membersWithShortfall++;
            }
        }

        $progressPercent = $totalExpected > 0
            ? min(100, round(($totalRealized / $totalExpected) * 100, 1))
            : 100;

        return [
            'period_label' => $periodLabel,
            'expected' => $totalExpected,
            'realized' => $totalRealized,
            'shortfall' => $totalShortfall,
            'progress_percent' => $progressPercent,
            'members_with_shortfall' => $membersWithShortfall,
            'total_members' => count($unrealized),
        ];
    }
}
