<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Balance Integrity Check</x-slot>
        <x-slot name="description">Compares stored balances against computed balances from transactions</x-slot>

        @php $results = $this->getCheckResults(); @endphp

        @if ($results['is_healthy'])
            <div class="flex items-center gap-3 py-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-success-50 dark:bg-success-500/10">
                    <x-filament::icon icon="heroicon-o-check-circle" class="h-6 w-6 text-success-600 dark:text-success-400" />
                </div>
                <div>
                    <p class="font-semibold text-success-600 dark:text-success-400">All balances match</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $results['total_members'] }} member(s) verified — no discrepancies found.
                    </p>
                </div>
            </div>
        @else
            <div class="space-y-4">
                @if (count($results['bank_mismatches']) > 0)
                    <div>
                        <p class="text-sm font-semibold text-danger-600 dark:text-danger-400 mb-2">
                            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-4 h-4 inline" />
                            {{ count($results['bank_mismatches']) }} bank balance mismatch(es)
                        </p>
                        <div class="overflow-hidden rounded-lg border border-danger-200 dark:border-danger-700">
                            <table class="w-full text-sm">
                                <thead class="bg-danger-50 dark:bg-danger-900/20">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Member</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-200">Stored</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-200">Computed</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-200">Diff</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($results['bank_mismatches'] as $m)
                                        <tr>
                                            <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $m['member'] }}</td>
                                            <td class="px-3 py-2 text-right">${{ number_format($m['stored'], 2) }}</td>
                                            <td class="px-3 py-2 text-right">${{ number_format($m['computed'], 2) }}</td>
                                            <td class="px-3 py-2 text-right font-semibold {{ $m['diff'] < 0 ? 'text-danger-600' : 'text-warning-600' }}">
                                                {{ $m['diff'] >= 0 ? '+' : '' }}${{ number_format($m['diff'], 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if (count($results['fund_mismatches']) > 0)
                    <div>
                        <p class="text-sm font-semibold text-warning-600 dark:text-warning-400 mb-2">
                            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-4 h-4 inline" />
                            {{ count($results['fund_mismatches']) }} fund balance mismatch(es)
                        </p>
                        <div class="overflow-hidden rounded-lg border border-warning-200 dark:border-warning-700">
                            <table class="w-full text-sm">
                                <thead class="bg-warning-50 dark:bg-warning-900/20">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Member</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-200">Stored</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-200">Computed</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-200">Diff</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($results['fund_mismatches'] as $m)
                                        <tr>
                                            <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $m['member'] }}</td>
                                            <td class="px-3 py-2 text-right">${{ number_format($m['stored'], 2) }}</td>
                                            <td class="px-3 py-2 text-right">${{ number_format($m['computed'], 2) }}</td>
                                            <td class="px-3 py-2 text-right font-semibold {{ $m['diff'] < 0 ? 'text-danger-600' : 'text-warning-600' }}">
                                                {{ $m['diff'] >= 0 ? '+' : '' }}${{ number_format($m['diff'], 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
