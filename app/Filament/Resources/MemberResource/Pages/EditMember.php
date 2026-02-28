<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Models\Member;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\On;

class EditMember extends EditRecord
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
        $this->refreshFormData(['bank_account_balance', 'fund_account_balance', 'outstanding_loans']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('recalculate_balance')
                ->label('Recalculate Balance')
                ->icon('heroicon-o-calculator')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Recalculate bank balance from transactions')
                ->modalDescription('This will set Bank Account Balance to the sum of transaction effects (credits minus debits) for this member. Use when the balance is out of sync.')
                ->action(function (): void {
                    /** @var Member $member */
                    $member = $this->record;
                    $balance = $member->recalculateBankAccountBalanceFromTransactions();
                    $this->record = $member->fresh();
                    $this->refreshFormData(['bank_account_balance']);
                    Notification::make()
                        ->title('Bank balance recalculated')
                        ->body('New balance: $' . number_format($balance, 2))
                        ->success()
                        ->send();
                }),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
