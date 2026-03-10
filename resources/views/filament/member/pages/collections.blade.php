<x-filament-panels::page>
    @php
        $member = $this->getMember();
        $summary = $this->getCollectionsSummary();
        $allocIn = $this->getAllocationsFromParent();
        $allocOut = $this->getAllocationsToDependants();
    @endphp

    @if (! $member)
        <x-filament::section>
            <x-slot name="heading">No member profile</x-slot>
            <x-slot name="description">This account is not linked to a member. Please contact an administrator.</x-slot>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">Collections status ({{ $summary['period_label'] }})</x-slot>
            <x-slot name="description">Your expected vs realized contributions and loan repayments for the current collection period.</x-slot>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Expected contribution</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-gray-100">
                        ${{ number_format($summary['expected_contribution'], 2) }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Expected loan repayments</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-gray-100">
                        ${{ number_format($summary['expected_repayment'], 2) }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Realized so far</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-gray-100">
                        ${{ number_format($summary['realized_contribution'] + $summary['realized_repayment'], 2) }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Shortfall</p>
                    <p class="text-xl font-bold {{ $summary['shortfall'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}">
                        ${{ number_format($summary['shortfall'], 2) }}
                    </p>
                </div>
            </div>
        </x-filament::section>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
            <x-filament::section>
                <x-slot name="heading">Allocations received from parent</x-slot>
                <x-slot name="description">Most recent allocations credited to your member bank account.</x-slot>

                @if($allocIn->isEmpty())
                    <p class="py-4 text-sm text-gray-500 dark:text-gray-400">No allocations received from parent yet.</p>
                @else
                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Date</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Amount</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                                @foreach($allocIn as $tx)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                            {{ $tx->transaction_date?->format('M j, Y') ?? $tx->created_at?->format('M j, Y') }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">
                                            ${{ number_format((float) $tx->amount, 2) }}
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

            <x-filament::section>
                <x-slot name="heading">Allocations to dependants</x-slot>
                <x-slot name="description">Recent allocations you have made to your dependants (if any).</x-slot>

                @if($allocOut->isEmpty())
                    <p class="py-4 text-sm text-gray-500 dark:text-gray-400">No allocations to dependants yet.</p>
                @else
                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Date</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Amount</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                                @foreach($allocOut as $tx)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                            {{ $tx->transaction_date?->format('M j, Y') ?? $tx->created_at?->format('M j, Y') }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">
                                            ${{ number_format((float) $tx->amount, 2) }}
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
        </div>
    @endif
</x-filament-panels::page>

