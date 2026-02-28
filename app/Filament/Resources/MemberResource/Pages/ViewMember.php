<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use Filament\Actions;
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
        return [
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
