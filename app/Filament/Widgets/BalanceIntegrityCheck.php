<?php

namespace App\Filament\Widgets;

use App\Models\Member;
use Filament\Widgets\Widget;

class BalanceIntegrityCheck extends Widget
{
    protected static ?int $sort = 10;
    protected int|string|array $columnSpan = 'full';
    protected string $view = 'filament.widgets.balance-integrity-check';

    public function getCheckResults(): array
    {
        $memberIds = Member::whereHas('user')->pluck('id');

        $bankMismatches = [];
        $fundMismatches = [];

        foreach ($memberIds as $memberId) {
            /** @var Member $member */
            $member = Member::with('user')->find($memberId);

            if (!$member || !$member->user) {
                continue;
            }

            $computedBank = $member->computeBankAccountBalanceFromTransactions();
            $storedBank = (float) $member->bank_account_balance;

            if (abs($computedBank - $storedBank) > 0.01) {
                $bankMismatches[] = [
                    'member' => $member->user->name ?? "Member #{$member->id}",
                    'stored' => $storedBank,
                    'computed' => $computedBank,
                    'diff' => $computedBank - $storedBank,
                ];
            }

            $computedFund = $member->computeFundAccountBalanceFromTransactions();
            $storedFund = (float) $member->fund_account_balance;

            if (abs($computedFund - $storedFund) > 0.01) {
                $fundMismatches[] = [
                    'member' => $member->user->name ?? "Member #{$member->id}",
                    'stored' => $storedFund,
                    'computed' => $computedFund,
                    'diff' => $computedFund - $storedFund,
                ];
            }
        }

        return [
            'total_members' => $memberIds->count(),
            'bank_mismatches' => $bankMismatches,
            'fund_mismatches' => $fundMismatches,
            'is_healthy' => empty($bankMismatches) && empty($fundMismatches),
        ];
    }
}
