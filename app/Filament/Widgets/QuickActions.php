<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AccountManagement;
use App\Filament\Pages\DailyReconciliation;
use App\Filament\Pages\ImportExternalBank;
use App\Filament\Resources\LoanResource;
use App\Filament\Resources\MemberResource;
use Filament\Widgets\Widget;

class QuickActions extends Widget
{
    protected string $view = 'filament.widgets.quick-actions';

    protected static ?int $sort = 0;
    protected int|string|array $columnSpan = 'full';

    public function getMemberIndexUrl(): string
    {
        return MemberResource::getUrl('index');
    }

    public function getMemberCreateUrl(): string
    {
        return MemberResource::getUrl('create');
    }

    public function getLoanIndexUrl(): string
    {
        return LoanResource::getUrl('index');
    }

    public function getImportBankUrl(): string
    {
        return ImportExternalBank::getUrl();
    }

    public function getDailyReconciliationUrl(): string
    {
        return DailyReconciliation::getUrl();
    }

    public function getAccountManagementUrl(): string
    {
        return AccountManagement::getUrl();
    }
}

