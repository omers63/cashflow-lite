<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MemberResource;
use App\Models\Setting;
use App\Services\MasterFundProjectionService;
use App\Services\MonthlyCollectionsService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class MonthlyCollections extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Collections';
    protected static ?string $title = 'Monthly Collections & Allocations';
    protected static string|\UnitEnum|null $navigationGroup = 'Financial Operations';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.pages.monthly-collections';

    public int $year;
    public int $month;

    /** Target master fund balance for "when will we reach it" projection. */
    public ?string $targetBalanceAmount = null;

    public function mount(): void
    {
        $this->year = (int) now()->format('Y');
        $this->month = (int) now()->subMonth()->format('n');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Monthly Collections & Allocations';
    }

    public function getPeriodLabel(): string
    {
        $m = Carbon::create($this->year, $this->month, 1);

        return $m->format('F Y');
    }

    public function getDueDate(): ?Carbon
    {
        $service = app(MonthlyCollectionsService::class);

        return $service->getDueDate($this->year, $this->month);
    }

    public function getUnrealized(): array
    {
        $service = app(MonthlyCollectionsService::class);

        return $service->getUnrealizedMembers($this->year, $this->month);
    }

    public function getUnallocated(): array
    {
        $service = app(MonthlyCollectionsService::class);

        return $service->getUnallocatedDependants($this->year, $this->month);
    }

    public function getUnrealizedCount(): int
    {
        return count($this->getUnrealized());
    }

    public function getUnallocatedCount(): int
    {
        return count($this->getUnallocated());
    }

    public function getTotalShortfall(): float
    {
        return array_sum(array_column($this->getUnrealized(), 'shortfall'));
    }

    /**
     * Projected master fund balance for the current period (running collections + queue disbursements).
     *
     * @return array{current_balance: float, projected_contributions: float, projected_repayments: float, pending_disbursements: float, loan_queue_count: int, projected_balance: float, loan_queue: \Illuminate\Support\Collection, period_label: string}
     */
    public function getMasterFundProjection(): array
    {
        return app(MasterFundProjectionService::class)->getProjection($this->year, $this->month);
    }

    /**
     * When will the master fund reach the entered target balance? Based on running contributions,
     * loan repayments, and loan queue disbursements. Returns null if no valid target entered.
     *
     * @return array|null
     */
    public function getTargetReachResult(): ?array
    {
        $amount = $this->targetBalanceAmount;
        if ($amount === null || $amount === '') {
            return null;
        }
        $value = (float) preg_replace('/[^0-9.-]/', '', $amount);
        if ($value <= 0) {
            return null;
        }

        $maxMonths = Setting::getInt('max_projection_months', 120);

        return app(MasterFundProjectionService::class)->getTargetReachProjection($value, max(1, $maxMonths));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('run_allocations')
                ->label('Run allocations for this month')
                ->icon('heroicon-o-user-group')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Run allocations for ' . $this->getPeriodLabel() . '?')
                ->modalDescription('This will create allocation transactions (parent → dependant) for all unallocated dependants, using the due date (5th of next month). Parents must have sufficient bank balance.')
                ->modalSubmitActionLabel('Run allocations')
                ->action(function (): void {
                    $service = app(MonthlyCollectionsService::class);
                    $result = $service->runAllocationsForPeriod($this->year, $this->month);
                    $msg = "{$result['processed']} allocation(s) processed.";
                    if ($result['skipped_insufficient'] > 0) {
                        $msg .= " {$result['skipped_insufficient']} skipped (insufficient balance).";
                    }
                    if (!empty($result['errors'])) {
                        $msg .= ' ' . implode(' ', array_slice($result['errors'], 0, 3));
                    }
                    Notification::make()->title('Allocations run')->body($msg)->success()->send();
                    // Force Livewire to re-render so Unallocated dependants & stats update immediately.
                    $this->dispatch('$refresh');
                }),

            Action::make('run_collections')
                ->label('Run contributions & repayments')
                ->icon('heroicon-o-banknotes')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Run contributions & loan repayments for ' . $this->getPeriodLabel() . '?')
                ->modalDescription('This will create contribution and loan repayment transactions for all members with unrealized collections, using the due date. Run allocations first so dependants have bank balance. Members must have sufficient balance.')
                ->modalSubmitActionLabel('Run collections')
                ->action(function (): void {
                    $service = app(MonthlyCollectionsService::class);
                    $result = $service->runContributionsAndRepaymentsForPeriod($this->year, $this->month);
                    $msg = "{$result['contributions']} contribution(s), {$result['repayments']} repayment(s) processed.";
                    if ($result['skipped_insufficient'] > 0) {
                        $msg .= " {$result['skipped_insufficient']} skipped (insufficient balance).";
                    }
                    if (!empty($result['errors'])) {
                        Notification::make()->title('Some errors')->body(implode("\n", array_slice($result['errors'], 0, 5)))->danger()->send();
                    }
                    Notification::make()->title('Collections run')->body($msg)->success()->send();
                    // Refresh Livewire state so Unrealized collections, stats, and projections update.
                    $this->dispatch('$refresh');
                }),
        ];
    }
}
