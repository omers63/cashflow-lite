<?php

namespace App\Filament\Widgets;

use App\Models\ExternalBankImport;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExternalBankImportsSummary extends BaseWidget
{
    protected static ?int $sort = 9;

    protected function getStats(): array
    {
        $unprocessed = ExternalBankImport::where('imported_to_master', false)
            ->where('is_duplicate', false)
            ->get();

        $unprocessedCount = $unprocessed->count();
        $unprocessedAmount = $unprocessed->sum('amount');

        $todayCount = ExternalBankImport::whereDate('import_date', today())->count();
        $todayAmount = (float) ExternalBankImport::whereDate('import_date', today())->sum('amount');

        $duplicatesCount = ExternalBankImport::where('is_duplicate', true)->count();

        return [
            Stat::make('Unprocessed Imports', $unprocessedCount)
                ->description('$' . number_format($unprocessedAmount, 2) . ' pending')
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color($unprocessedCount > 0 ? 'warning' : 'success')
                ->icon('heroicon-m-inbox-arrow-down'),

            Stat::make("Today's Imports", $todayCount)
                ->description('$' . number_format($todayAmount, 2) . ' imported today')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info')
                ->icon('heroicon-m-calendar-days'),

            Stat::make('Marked Duplicates', $duplicatesCount)
                ->description('Flagged as duplicates')
                ->descriptionIcon('heroicon-m-document-duplicate')
                ->color($duplicatesCount > 0 ? 'gray' : 'success')
                ->icon('heroicon-m-document-duplicate'),
        ];
    }
}
