<x-filament-panels::page>
    @php
        $member = $this->getMember();
        $ledger = $this->getLedger();
    @endphp

    @if (! $member)
        <x-filament::section>
            <x-slot name="heading">No member profile</x-slot>
            <x-slot name="description">This account is not linked to a member. Please contact an administrator.</x-slot>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">Account balances</x-slot>
            <x-slot name="description">Your current member bank and fund balances.</x-slot>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Member Bank Account</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        ${{ number_format((float) $member->bank_account_balance, 2) }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Member Fund Account</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        ${{ number_format((float) $member->fund_account_balance, 2) }}
                    </p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section class="mt-6">
            <x-slot name="heading">Account activity</x-slot>
            <x-slot name="description">Recent movements on your member bank and fund accounts.</x-slot>

            @if($ledger->isEmpty())
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
                            @foreach($ledger as $tx)
                                <tr>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                        {{ $tx->transaction_date?->format('M j, Y') ?? $tx->created_at?->format('M j, Y') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ str_replace('_', ' ', ucfirst($tx->type)) }}
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

