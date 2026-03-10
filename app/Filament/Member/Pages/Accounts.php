<?php

namespace App\Filament\Member\Pages;

use App\Models\Member;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class Accounts extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Accounts';
    protected static string|\UnitEnum|null $navigationGroup = 'Accounts';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.member.pages.accounts';

    public function getTitle(): string|Htmlable
    {
        return 'Accounts';
    }

    public function getMember(): ?Member
    {
        return auth()->user()?->member;
    }

    /**
     * Recent transactions for the account ledger.
     *
     * @return \Illuminate\Support\Collection<int, Transaction>
     */
    public function getLedger(): Collection
    {
        $member = $this->getMember();
        if (!$member) {
            return collect();
        }

        return Transaction::query()
            ->where('user_id', $member->user_id)
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    /**
     * Member-side transactional actions for accounts (e.g. contribute).
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('contribute')
                ->label('Contribute now')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->form(function () {
                    $member = $this->getMember();
                    $default = $member ? (float) ($member->allowed_allocation ?? 500) : 0.0;

                    return [
                        \Filament\Forms\Components\TextInput::make('amount')
                            ->label('Contribution amount')
                            ->numeric()
                            ->required()
                            ->prefix('$')
                            ->minValue(1)
                            ->default($default)
                            ->helperText($member
                                ? 'Allowed allocation: $' . number_format((float) ($member->allowed_allocation ?? 500), 2)
                                : null),
                    ];
                })
                ->action(function (array $data): void {
                    $member = $this->getMember();
                    if (!$member) {
                        Notification::make()->title('No member profile')->danger()->send();
                        return;
                    }
                    $amount = (float) ($data['amount'] ?? 0);
                    try {
                        $member->contribute($amount > 0 ? $amount : null, 'Member portal contribution');
                        Notification::make()
                            ->title('Contribution posted')
                            ->body('Your contribution has been recorded.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Contribution failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}

