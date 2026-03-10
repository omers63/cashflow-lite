<x-filament-panels::page>
    @php
        $member = $this->getMember();
        $summary = $this->getSummary();
        $recent = $this->getRecentTransactions();
    @endphp

    @if (! $member)
        <x-filament::section>
            <x-slot name="heading">No member profile</x-slot>
            <x-slot name="description">This account is not linked to a member. Please contact an administrator.</x-slot>
        </x-filament::section>
    @else
        {{-- Summary cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <x-filament::section class="!p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Bank balance</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    ${{ number_format($summary['bank_balance'], 2) }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Member Bank Account</p>
            </x-filament::section>

            <x-filament::section class="!p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Fund balance</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    ${{ number_format($summary['fund_balance'], 2) }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Member Fund Account</p>
            </x-filament::section>

            <x-filament::section class="!p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Outstanding loans</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    ${{ number_format($summary['outstanding_loans'], 2) }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ $member->loans()->where('status', 'active')->count() }} active loan(s)
                </p>
            </x-filament::section>

            <x-filament::section class="!p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Next payment</p>
                @if($summary['next_payment_date'])
                    <p class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        {{ $summary['next_payment_date']->format('M j, Y') }}
                    </p>
                    @if($summary['next_payment_amount'])
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            ${{ number_format($summary['next_payment_amount'], 2) }}
                        </p>
                    @endif
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">No upcoming loan payments.</p>
                @endif
            </x-filament::section>
        </div>

        {{-- Collections status --}}
        <x-filament::section>
            <x-slot name="heading">Collections status ({{ $summary['period_label'] }})</x-slot>
            <x-slot name="description">
                Expected vs realized contributions and loan repayments for the current collection period.
            </x-slot>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="space-y-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Expected total (contribution + repayments)</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-gray-100">
                        ${{ number_format($summary['expected_total'], 2) }}
                    </p>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Realized so far</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-gray-100">
                        ${{ number_format($summary['realized_total'], 2) }}
                    </p>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Shortfall</p>
                    <p class="text-xl font-bold {{ $summary['shortfall'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}">
                        ${{ number_format($summary['shortfall'], 2) }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $summary['shortfall'] > 0 ? 'Additional funds needed to be fully up to date.' : 'You are fully up to date for this period.' }}
                    </p>
                </div>
            </div>
        </x-filament::section>

        {{-- Recent activity --}}
        <x-filament::section class="mt-6">
            <x-slot name="heading">Recent activity</x-slot>
            <x-slot name="description">Latest movements on your member bank and fund accounts.</x-slot>

            @if($recent->isEmpty())
                <p class="py-4 text-sm text-gray-500 dark:text-gray-400">No recent transactions.</p>
            @else
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Date</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Type</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">From</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">To</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Amount</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                            @foreach($recent as $tx)
                                <tr>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                        {{ $tx->transaction_date?->format('M j, Y') ?? $tx->created_at?->format('M j, Y') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                            @class([
                                                'bg-green-100 text-green-800' => in_array($tx->type, ['contribution', 'loan_repayment', 'allocation_from_parent']),
                                                'bg-blue-100 text-blue-800' => in_array($tx->type, ['allocation_to_dependant']),
                                                'bg-gray-100 text-gray-800' => ! in_array($tx->type, ['contribution', 'loan_repayment', 'allocation_from_parent', 'allocation_to_dependant']),
                                            ])
                                        ">
                                            {{ str_replace('_', ' ', ucfirst($tx->type)) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                        {{ $tx->from_account ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                        {{ $tx->to_account ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold">
                                        <span class="{{ $tx->amount < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-900 dark:text-gray-100' }}">
                                            ${{ number_format((float) $tx->amount, 2) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                        {{ $tx->notes ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>

