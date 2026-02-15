<?php

namespace App\Filament\Widgets;

use App\Models\Exception;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OpenExceptions extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $open = Exception::open()->count();
        $overdue = Exception::overdue()->count();
        $critical = Exception::where('severity', 'critical')->open()->count();

        return [
            Stat::make('Open Exceptions', $open)
                ->description($overdue . ' overdue')
                ->color($open > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle'),
                
            Stat::make('Critical Issues', $critical)
                ->description('Requires immediate attention')
                ->color($critical > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-fire'),
        ];
    }
}
