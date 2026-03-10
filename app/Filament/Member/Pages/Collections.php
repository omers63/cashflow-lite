<?php

namespace App\Filament\Member\Pages;

use App\Models\Member;
use App\Models\Transaction;
use App\Services\MonthlyCollectionsService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class Collections extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Collections';
    protected static string|\UnitEnum|null $navigationGroup = 'Collections & Allocations';
    protected static ?int $navigationSort = 3;
    protected string $view = 'filament.member.pages.collections';

    public function getTitle(): string|Htmlable
    {
        return 'Collections & Allocations';
    }

    public function getMember(): ?Member
    {
        return auth()->user()?->member;
    }

    public function getCurrentPeriod(): array
    {
        $now = Carbon::now();
        $month = (int) $now->copy()->subMonth()->format('n');
        $year = (int) $now->copy()->subMonth()->format('Y');

        return [$year, $month];
    }

    public function getCollectionsSummary(): array
    {
        $member = $this->getMember();
        if (!$member) {
            return [
                'expected_contribution' => 0.0,
                'expected_repayment' => 0.0,
                'realized_contribution' => 0.0,
                'realized_repayment' => 0.0,
                'shortfall' => 0.0,
                'period_label' => '',
            ];
        }

        [$year, $month] = $this->getCurrentPeriod();
        $periodLabel = Carbon::create($year, $month, 1)->format('F Y');

        $service = app(MonthlyCollectionsService::class);
        $rows = $service->getUnrealizedMembers($year, $month);
        $row = $rows[$member->id] ?? null;

        return [
            'expected_contribution' => (float) ($row['expected_contribution'] ?? 0.0),
            'expected_repayment' => (float) ($row['expected_repayment'] ?? 0.0),
            'realized_contribution' => (float) ($row['realized_contribution'] ?? 0.0),
            'realized_repayment' => (float) ($row['realized_repayment'] ?? 0.0),
            'shortfall' => (float) ($row['shortfall'] ?? 0.0),
            'period_label' => $periodLabel,
        ];
    }

    /**
     * Allocations received from parent for this member.
     */
    public function getAllocationsFromParent(): Collection
    {
        $member = $this->getMember();
        if (!$member) {
            return collect();
        }

        return Transaction::query()
            ->where('user_id', $member->user_id)
            ->where('type', 'allocation_from_parent')
            ->orderByDesc('transaction_date')
            ->limit(20)
            ->get();
    }

    /**
     * Allocations made to dependants (if this member is a parent).
     */
    public function getAllocationsToDependants(): Collection
    {
        $member = $this->getMember();
        if (!$member) {
            return collect();
        }

        return Transaction::query()
            ->where('user_id', $member->user_id)
            ->where('type', 'allocation_to_dependant')
            ->orderByDesc('transaction_date')
            ->limit(20)
            ->get();
    }

    /**
     * Member-side allocation action (for parents).
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('allocate_to_dependant')
                ->label('Allocate to dependant')
                ->icon('heroicon-o-user-group')
                ->color('primary')
                ->form(function () {
                    $member = $this->getMember();
                    if (! $member) {
                        return [];
                    }
                    $dependants = $member->dependants()->with('user')->get();
                    if ($dependants->isEmpty()) {
                        return [
                            \Filament\Forms\Components\Placeholder::make('_no_dependants')
                                ->label('No dependants')
                                ->content('You do not have any dependants configured.'),
                        ];
                    }
                    return [
                        \Filament\Forms\Components\Select::make('dependant_id')
                            ->label('Dependant')
                            ->options($dependants->mapWithKeys(fn (Member $d) => [
                                $d->id => $d->user?->name ?? "Member #{$d->id}",
                            ]))
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('amount')
                            ->label('Allocation amount')
                            ->numeric()
                            ->required()
                            ->prefix('$')
                            ->minValue(1),
                    ];
                })
                ->action(function (array $data): void {
                    $member = $this->getMember();
                    if (! $member) {
                        Notification::make()->title('No member profile')->danger()->send();
                        return;
                    }
                    $dependantId = (int) ($data['dependant_id'] ?? 0);
                    $amount = (float) ($data['amount'] ?? 0);
                    $dependant = $member->dependants()->find($dependantId);
                    if (! $dependant) {
                        Notification::make()->title('Invalid dependant')->danger()->send();
                        return;
                    }
                    if ($amount <= 0) {
                        Notification::make()->title('Invalid amount')->danger()->send();
                        return;
                    }
                    try {
                        $member->allocateToDependant($dependant, $amount, null);
                        Notification::make()
                            ->title('Allocation posted')
                            ->body("Allocation of $" . number_format($amount, 2) . " to {$dependant->user?->name} recorded.")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Allocation failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}

