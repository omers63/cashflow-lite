<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Models\Member;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewMember extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    #[On('refreshMemberRecord')]
    public function refreshMemberRecord(?int $memberId = null): void
    {
        if ($memberId !== null && $this->record->getKey() !== $memberId) {
            return;
        }
        $fresh = $this->record->fresh();
        if ($fresh) {
            $this->record = $fresh;
        } else {
            $this->record->refresh();
        }
    }

    protected function getHeaderActions(): array
    {
        /** @var Member $member */
        $member = $this->record;

        $dependants = $member->dependants()->with('user')->get();

        return [
            Actions\Action::make('set_allowances')
                ->label('Set Allowances')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('info')
                ->visible(fn () => $member->isParentMember())
                ->form(function () use ($member, $dependants) {
                    $allocationOptions = array_combine(
                        \App\Models\Member::ALLOCATION_OPTIONS,
                        array_map(fn ($v) => '$' . number_format($v, 0), \App\Models\Member::ALLOCATION_OPTIONS)
                    );
                    $fields = [
                        Forms\Components\Select::make("allowance_{$member->id}")
                            ->label(($member->user ? "{$member->user->name} ({$member->user->user_code})" : "Member #{$member->id}") . ' (yourself)')
                            ->options($allocationOptions)
                            ->default((int) ($member->allowed_allocation ?? 500))
                            ->required(),
                    ];
                    foreach ($dependants as $d) {
                        $fields[] = Forms\Components\Select::make("allowance_{$d->id}")
                            ->label($d->user ? "{$d->user->name} ({$d->user->user_code})" : "Member #{$d->id}")
                            ->options($allocationOptions)
                            ->default((int) ($d->allowed_allocation ?? 500))
                            ->required();
                    }
                    return $fields;
                })
                ->action(function (array $data) use ($member, $dependants): void {
                    $member->update(['allowed_allocation' => (int) $data["allowance_{$member->id}"]]);
                    foreach ($dependants as $d) {
                        $d->update(['allowed_allocation' => (int) $data["allowance_{$d->id}"]]);
                    }
                    $this->record = $member->fresh();
                    Notification::make()
                        ->title('Allowances saved')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('allocate_to_dependant')
                ->label('Allocate to Dependant')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->visible(fn () => $member->isParentMember())
                ->form([
                    Forms\Components\Select::make('dependent_id')
                        ->label('Dependant')
                        ->options(
                            $dependants->mapWithKeys(
                                fn (Member $d) => [$d->id => $d->user ? "{$d->user->name} ({$d->user->user_code})" : "Member #{$d->id}"]
                            )
                        )
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn ($set) => $set('amount', null)),
                    Forms\Components\TextInput::make('amount')
                        ->label('Amount')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->step(0.01)
                        ->prefix('$')
                        ->helperText(function ($get) use ($member, $dependants) {
                            $dep = $dependants->find((int) $get('dependent_id'));
                            $parentAllowance = (int) ($member->allowed_allocation ?? 500);
                            $depAllowance = (int) ($dep->allowed_allocation ?? 500);
                            $effectiveMax = min((float) $member->bank_account_balance, $parentAllowance, $depAllowance);
                            return 'Max: $' . number_format($effectiveMax, 2)
                                . ' — bank: $' . number_format((float) $member->bank_account_balance, 2)
                                . ', your allowance: $' . number_format($parentAllowance, 2)
                                . ($dep ? ', dependant allowance: $' . number_format($depAllowance, 2) : '');
                        })
                        ->live()
                        ->rule(fn ($get) => function (string $attribute, $value, \Closure $fail) use ($member, $dependants, $get) {
                            $dep = $dependants->find((int) $get('dependent_id'));
                            $parentAllowance = (int) ($member->allowed_allocation ?? 500);
                            $depAllowance = (int) ($dep->allowed_allocation ?? 500);
                            $effectiveMax = min((float) $member->bank_account_balance, $parentAllowance, $depAllowance);
                            if ((float) $value > $effectiveMax) {
                                $fail('Amount cannot exceed $' . number_format($effectiveMax, 2)
                                    . ' (bank: $' . number_format((float) $member->bank_account_balance, 2)
                                    . ', your allowance: $' . number_format($parentAllowance, 2)
                                    . ', dependant allowance: $' . number_format($depAllowance, 2) . ').');
                            }
                        }),
                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $member = $this->record;
                    $dependent = Member::find($data['dependent_id']);
                    if (! $dependent || $dependent->parent_id !== $member->id) {
                        Notification::make()->title('Invalid dependant')->danger()->send();
                        return;
                    }
                    try {
                        $member->allocateToDependant($dependent, (float) $data['amount'], $data['notes'] ?? null);
                        $this->record = $member->fresh();
                        Notification::make()
                            ->title('Allocation completed')
                            ->body('Funds allocated to ' . ($dependent->user?->name ?? "Member #{$dependent->id}") . '.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('recalculate_balance')
                ->label('Recalculate Balance')
                ->icon('heroicon-o-calculator')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Recalculate bank balance from transactions')
                ->modalDescription('This will set Bank Account Balance to the sum of transaction effects for this member.')
                ->action(function (): void {
                    $member = $this->record;
                    $balance = $member->recalculateBankAccountBalanceFromTransactions();
                    $this->record = $member->fresh();
                    \Filament\Notifications\Notification::make()
                        ->title('Bank balance recalculated')
                        ->body('New balance: $' . number_format($balance, 2))
                        ->success()
                        ->send();
                }),
            Actions\EditAction::make(),
        ];
    }
}
