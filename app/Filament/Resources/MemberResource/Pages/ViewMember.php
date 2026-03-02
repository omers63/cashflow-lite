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
        $this->refreshInfolist();
    }

    /**
     * Clear the cached infolist and re-fill it from the current record.
     * Required because Filament caches the infolist instance (including the
     * record it was built with), so simply updating $this->record is not enough.
     */
    protected function refreshInfolist(): void
    {
        $this->cachedInfolists = [];
        $this->fillForm();
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
                        ->helperText(function ($get) use ($member, $dependants) {
                            $dep = $dependants->find((int) $get('dependent_id'));
                            if (! $dep) {
                                return null;
                            }
                            $amount = (int) ($dep->allowed_allocation ?? 500);
                            $bank = (float) $member->bank_account_balance;
                            $sufficient = $bank >= $amount;
                            return 'Amount to allocate: $' . number_format($amount, 2)
                                . ' — your bank balance: $' . number_format($bank, 2)
                                . ($sufficient ? '' : ' ⚠ Insufficient balance');
                        }),
                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $member = $this->record->fresh();
                    $dependent = Member::find($data['dependent_id']);
                    if (! $dependent || $dependent->parent_id !== $member->id) {
                        Notification::make()->title('Invalid dependant')->danger()->send();
                        return;
                    }
                    $amount = (int) ($dependent->allowed_allocation ?? 500);
                    if ((float) $member->bank_account_balance < $amount) {
                        Notification::make()
                            ->title('Insufficient bank balance')
                            ->body('Need $' . number_format($amount, 2) . ', available $' . number_format((float) $member->bank_account_balance, 2) . '.')
                            ->danger()
                            ->send();
                        return;
                    }
                    try {
                        $member->allocateToDependant($dependent, (float) $amount, $data['notes'] ?? null);
                        $this->record = $member->fresh();
                        $this->refreshInfolist();
                        $this->dispatch('refreshTransactions');
                        Notification::make()
                            ->title('Allocation completed')
                            ->body('$' . number_format($amount, 2) . ' allocated to ' . ($dependent->user?->name ?? "Member #{$dependent->id}") . '.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('contribute')
                ->label('Contribute')
                ->icon('heroicon-o-arrow-up-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(fn () => 'Contribute $' . number_format((int) ($member->allowed_allocation ?? 500), 2))
                ->modalDescription(function () use ($member) {
                    $amount = (int) ($member->allowed_allocation ?? 500);
                    $bank = (float) $member->bank_account_balance;
                    return "This will debit your bank account by \${$amount} and credit your fund account by the same amount. "
                        . "Current bank balance: \$" . number_format($bank, 2) . ".";
                })
                ->disabled(fn () => (float) $member->bank_account_balance < (int) ($member->allowed_allocation ?? 500))
                ->tooltip(function () use ($member) {
                    if ((float) $member->bank_account_balance < (int) ($member->allowed_allocation ?? 500)) {
                        return 'Insufficient bank balance to contribute $' . number_format((int) ($member->allowed_allocation ?? 500), 2);
                    }
                    return null;
                })
                ->action(function (): void {
                    $member = $this->record->fresh();
                    $amount = (int) ($member->allowed_allocation ?? 500);
                    try {
                        $member->contribute();
                        $this->record = $member->fresh();
                        $this->refreshInfolist();
                        $this->dispatch('refreshTransactions');
                        Notification::make()
                            ->title('Contribution posted')
                            ->body('$' . number_format($amount, 2) . ' contributed to your fund account.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('import_funds')
                ->label('Import Funds')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('warning')
                ->form([
                    Forms\Components\FileUpload::make('import_file')
                        ->label('File (CSV / XLS / XLSX)')
                        ->helperText('Two columns required: Transaction Date, Amount. First row is the header and will be skipped.')
                        ->required()
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/csv',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/octet-stream',
                        ])
                        ->disk('local')
                        ->directory('imports/member-funds')
                        ->visibility('private'),
                    Forms\Components\Select::make('date_format')
                        ->label('Date Format')
                        ->required()
                        ->options([
                            'Y-m-d'  => 'YYYY-MM-DD  (e.g. 2026-01-15)',
                            'd/m/Y'  => 'DD/MM/YYYY  (e.g. 15/01/2026)',
                            'm/d/Y'  => 'MM/DD/YYYY  (e.g. 01/15/2026)',
                            'd-m-Y'  => 'DD-MM-YYYY  (e.g. 15-01-2026)',
                            'm-d-Y'  => 'MM-DD-YYYY  (e.g. 01-15-2026)',
                            'd/m/y'  => 'DD/MM/YY    (e.g. 15/01/26)',
                            'm/d/y'  => 'MM/DD/YY    (e.g. 01/15/26)',
                            'auto'   => 'Auto-detect',
                        ])
                        ->default('Y-m-d')
                        ->helperText('For Excel files with native date cells the format is detected automatically.'),
                ])
                ->action(function (array $data): void {
                    $member = $this->record->fresh();
                    $path = $data['import_file'];
                    $absolutePath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);
                    try {
                        $results = $member->importFunds($absolutePath, $data['date_format'] ?? 'Y-m-d');
                        $this->record = $member->fresh();
                        $this->refreshInfolist();
                        $this->dispatch('refreshTransactions');
                        \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
                        $body = $results['imported'] . ' row(s) imported.'
                            . ($results['skipped'] > 0 ? ' ' . $results['skipped'] . ' skipped.' : '')
                            . (count($results['errors']) > 0 ? ' Errors: ' . implode('; ', $results['errors']) : '');
                        Notification::make()
                            ->title('Import complete')
                            ->body($body)
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
                    $this->refreshInfolist();
                    $this->dispatch('refreshTransactions');
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
