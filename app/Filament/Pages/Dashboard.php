<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\QuickActions::class,
            \App\Filament\Widgets\StatsOverview::class,
            \App\Filament\Widgets\OpenExceptions::class,
            \App\Filament\Widgets\ReconciliationStatus::class,
            \App\Filament\Widgets\RecentTransactions::class,
        ];
    }
}
