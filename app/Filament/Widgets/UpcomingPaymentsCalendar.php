<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use Filament\Widgets\Widget;

class UpcomingPaymentsCalendar extends Widget
{
    protected static ?int $sort = 11;
    protected int|string|array $columnSpan = 'full';
    protected string $view = 'filament.widgets.upcoming-payments-calendar';

    public function getUpcomingPayments(): array
    {
        $loans = Loan::query()
            ->where('status', 'active')
            ->whereNotNull('next_payment_date')
            ->whereBetween('next_payment_date', [now(), now()->addDays(14)])
            ->with('member.user')
            ->orderBy('next_payment_date')
            ->get();

        $grouped = [];
        foreach ($loans as $loan) {
            $dateKey = $loan->next_payment_date->format('Y-m-d');
            $label = $loan->next_payment_date->format('D, M j');

            if (!isset($grouped[$dateKey])) {
                $grouped[$dateKey] = [
                    'label' => $label,
                    'is_today' => $loan->next_payment_date->isToday(),
                    'is_past' => $loan->next_payment_date->isPast(),
                    'items' => [],
                ];
            }

            $grouped[$dateKey]['items'][] = [
                'loan_id' => $loan->loan_id,
                'member' => $loan->member?->user?->name ?? 'Unknown',
                'amount' => (float) ($loan->installment_amount ?? $loan->monthly_payment),
            ];
        }

        return $grouped;
    }

    public function getTotalExpected(): float
    {
        return Loan::query()
            ->where('status', 'active')
            ->whereNotNull('next_payment_date')
            ->whereBetween('next_payment_date', [now(), now()->addDays(14)])
            ->sum('installment_amount');
    }
}
