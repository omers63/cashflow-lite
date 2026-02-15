<?php

namespace App\Filament\Pages;

use App\Services\ReconciliationService;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class DailyReconciliation extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static string $view = 'filament.pages.daily-reconciliation';
    protected static ?string $navigationGroup = 'Financial Operations';
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
        return [
            Action::make('run_reconciliation')
                ->label('Run Reconciliation')
                ->icon('heroicon-o-play')
                ->color('primary')
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
