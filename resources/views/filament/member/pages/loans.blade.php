<x-filament-panels::page>
    @php
        $member = $this->getMember();
        $loans = $this->getActiveLoans();
    @endphp

    @if (! $member)
        <x-filament::section>
            <x-slot name="heading">No member profile</x-slot>
            <x-slot name="description">This account is not linked to a member. Please contact an administrator.</x-slot>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">Your loans</x-slot>
            <x-slot name="description">Overview of your active and historical loans.</x-slot>

            @if($loans->isEmpty())
                <p class="py-4 text-sm text-gray-500 dark:text-gray-400">You do not currently have any loans.</p>
            @else
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Loan ID</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Original amount</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Outstanding</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Installment</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Next payment</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                            @foreach($loans as $loan)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-gray-900 dark:text-gray-100">{{ $loan->loan_id }}</td>
                                    <td class="px-4 py-3 text-right">${{ number_format((float) $loan->original_amount, 2) }}</td>
                                    <td class="px-4 py-3 text-right">${{ number_format((float) $loan->outstanding_balance, 2) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @php $inst = (float) ($loan->installment_amount ?? $loan->monthly_payment); @endphp
                                        ${{ number_format($inst, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                        @if($loan->next_payment_date)
                                            {{ $loan->next_payment_date->format('M j, Y') }}
                                        @else
                                            <span class="text-xs text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                            @class([
                                                'bg-green-100 text-green-800' => $loan->status === 'paid_off',
                                                'bg-yellow-100 text-yellow-800' => $loan->status === 'active',
                                                'bg-red-100 text-red-800' => $loan->status === 'defaulted',
                                                'bg-gray-100 text-gray-800' => ! in_array($loan->status, ['active', 'paid_off', 'defaulted']),
                                            ])
                                        ">
                                            {{ ucfirst(str_replace('_', ' ', $loan->status)) }}
                                        </span>
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

