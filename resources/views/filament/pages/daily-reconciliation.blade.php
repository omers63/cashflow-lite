<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Latest Daily Reconciliation --}}
        @if($latestReconciliation && $latestReconciliation['latest'])
            @php
                $latest = $latestReconciliation['latest'];
                $totalChecks = $latest->checks_passed + $latest->checks_failed;
                $checkResults = is_array($latest->check_results) ? $latest->check_results : [];
            @endphp
            <x-filament::section>
                <x-slot name="heading">Latest Reconciliation</x-slot>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h4 class="font-semibold mb-2">Latest daily — {{ \Carbon\Carbon::parse($latest->reconciliation_date)->format('M d, Y') }}</h4>
                        <p class="text-sm">Status:
                            <span class="@if($latest->all_passed) text-success-600 @else text-danger-600 @endif font-semibold">
                                {{ $latest->all_passed ? 'PASSED' : 'FAILED' }}
                            </span>
                        </p>
                        <p class="text-sm">Checks Passed: {{ $latest->checks_passed }}/{{ $totalChecks }}</p>
                        <p class="text-sm">Checks Failed: {{ $latest->checks_failed }}</p>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <a href="{{ \App\Filament\Resources\ReconciliationResource::getUrl('view', ['record' => $latest]) }}" class="text-sm text-primary-600 hover:underline">View reconciliation</a>
                            @if(!$latest->all_passed)
                                <a href="{{ \App\Filament\Resources\ExceptionResource::getUrl('index') }}?tableFilters[related_reconciliation_id]={{ $latest->id }}" class="text-sm text-danger-600 hover:underline">View exceptions</a>
                            @endif
                        </div>
                    </div>
                    <div>
                        <h4 class="font-semibold mb-2">This Month</h4>
                        <p class="text-sm">Total Reconciliations: {{ $latestReconciliation['month_total'] }}</p>
                        <p class="text-sm">Passed: {{ $latestReconciliation['month_passed'] }}</p>
                        <p class="text-sm">Failed: {{ $latestReconciliation['month_failed'] }}</p>
                        <p class="text-sm">Pass Rate: {{ $latestReconciliation['month_pass_rate'] }}%</p>
                    </div>
                </div>

                @if(count($checkResults) > 0)
                    <div class="mt-4 border rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium">Check</th>
                                    <th class="px-4 py-2 text-left font-medium">Description</th>
                                    <th class="px-4 py-2 text-center font-medium">Status</th>
                                    <th class="px-4 py-2 text-right font-medium">Variance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($checkResults as $check)
                                    <tr>
                                        <td class="px-4 py-2 font-mono font-medium">{{ $check['key'] ?? $loop->iteration }}</td>
                                        <td class="px-4 py-2">{{ $check['name'] ?? '-' }}</td>
                                        <td class="px-4 py-2 text-center">
                                            @if(($check['status'] ?? '') === 'PASS')
                                                <span class="inline-flex items-center rounded-md bg-success-500/10 px-2 py-0.5 text-success-700 dark:text-success-400">PASS</span>
                                            @else
                                                <span class="inline-flex items-center rounded-md bg-danger-500/10 px-2 py-0.5 text-danger-700 dark:text-danger-400">FAIL</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            @if(isset($check['variance']) && (float) $check['variance'] != 0)
                                                ${{ number_format((float) $check['variance'], 2) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        @endif

        {{-- Latest Monthly Reconciliation --}}
        @if($latestReconciliation && ($latestMonthly = $latestReconciliation['latest_monthly'] ?? null))
            <x-filament::section>
                <x-slot name="heading">Latest monthly reconciliation</x-slot>
                <div class="flex flex-wrap items-center gap-4">
                    <p class="text-sm">Period: <strong>{{ \Carbon\Carbon::parse($latestMonthly->reconciliation_date)->format('F Y') }}</strong></p>
                    <p class="text-sm">Status:
                        <span class="@if($latestMonthly->all_passed) text-success-600 @else text-danger-600 @endif font-semibold">
                            {{ $latestMonthly->all_passed ? 'PASSED' : 'FAILED' }}
                        </span>
                    </p>
                    <p class="text-sm">Checks: {{ $latestMonthly->checks_passed }}/{{ $latestMonthly->checks_passed + $latestMonthly->checks_failed }} passed</p>
                    <a href="{{ \App\Filament\Resources\ReconciliationResource::getUrl('view', ['record' => $latestMonthly]) }}" class="text-sm text-primary-600 hover:underline">View</a>
                </div>
            </x-filament::section>
        @endif

        {{-- Latest Balance Snapshot --}}
        @if($latestReconciliation && ($snap = $latestReconciliation['latest_snapshot'] ?? null))
            <x-filament::section>
                <x-slot name="heading">Latest balance snapshot</x-slot>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div><span class="text-gray-500">Date</span><br><strong>{{ \Carbon\Carbon::parse($snap->snapshot_date)->format('F j, Y') }}</strong></div>
                    <div><span class="text-gray-500">Master Bank</span><br><strong>${{ number_format($snap->master_bank, 2) }}</strong></div>
                    <div><span class="text-gray-500">Master Fund</span><br><strong>${{ number_format($snap->master_fund, 2) }}</strong></div>
                    <div><span class="text-gray-500">Outstanding Loans</span><br><strong>${{ number_format($snap->outstanding_loans_total, 2) }}</strong></div>
                </div>
                <div class="mt-2">
                    <a href="{{ \App\Filament\Resources\BalanceSnapshotResource::getUrl('index') }}" class="text-sm text-primary-600 hover:underline">View all snapshots</a>
                </div>
            </x-filament::section>
        @endif

        {{-- Current System Totals --}}
        @if($systemTotals)
            <x-filament::section>
                <x-slot name="heading">Current System Totals</x-slot>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Master Bank</p>
                        <p class="text-lg font-semibold">${{ number_format($systemTotals['master_bank'], 2) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Master Fund</p>
                        <p class="text-lg font-semibold">${{ number_format($systemTotals['master_fund'], 2) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Member Banks Total</p>
                        <p class="text-lg font-semibold">${{ number_format($systemTotals['user_banks_total'], 2) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Outstanding Loans</p>
                        <p class="text-lg font-semibold">${{ number_format($systemTotals['outstanding_loans_total'], 2) }}</p>
                    </div>
                </div>
            </x-filament::section>
        @endif

        {{-- Recent Reconciliations --}}
        @if(count($recentReconciliations))
            <x-filament::section>
                <x-slot name="heading">Recent Reconciliations</x-slot>
                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium">Date</th>
                                <th class="px-4 py-2 text-left font-medium">Type</th>
                                <th class="px-4 py-2 text-center font-medium">Status</th>
                                <th class="px-4 py-2 text-center font-medium">Passed</th>
                                <th class="px-4 py-2 text-right font-medium">Variance</th>
                                <th class="px-4 py-2 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($recentReconciliations as $rec)
                                <tr>
                                    <td class="px-4 py-2">{{ $rec['reconciliation_date'] }}</td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium
                                            @if($rec['type'] === 'daily') bg-primary-500/10 text-primary-700 dark:text-primary-400
                                            @elseif($rec['type'] === 'monthly') bg-success-500/10 text-success-700 dark:text-success-400
                                            @else bg-gray-500/10 text-gray-700 dark:text-gray-300 @endif">
                                            {{ ucfirst($rec['type']) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        @if($rec['all_passed'])
                                            <span class="inline-flex items-center rounded-md bg-success-500/10 px-2 py-0.5 text-success-700 dark:text-success-400">PASSED</span>
                                        @else
                                            <span class="inline-flex items-center rounded-md bg-danger-500/10 px-2 py-0.5 text-danger-700 dark:text-danger-400">FAILED</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-center">{{ $rec['checks_passed'] }}/{{ $rec['checks_passed'] + $rec['checks_failed'] }}</td>
                                    <td class="px-4 py-2 text-right">${{ number_format($rec['total_variance'], 2) }}</td>
                                    <td class="px-4 py-2 text-right space-x-2">
                                        <a href="{{ $rec['view_url'] }}" class="text-xs text-primary-600 hover:underline">View</a>
                                        @if(!$rec['all_passed'])
                                            <a href="{{ $rec['exceptions_url'] }}" class="text-xs text-danger-600 hover:underline">Exceptions</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        {{-- Open Exceptions --}}
        @if(count($openExceptions))
            <x-filament::section>
                <x-slot name="heading">Open Exceptions</x-slot>
                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium">ID</th>
                                <th class="px-4 py-2 text-left font-medium">Type</th>
                                <th class="px-4 py-2 text-left font-medium">Severity</th>
                                <th class="px-4 py-2 text-left font-medium">Status</th>
                                <th class="px-4 py-2 text-left font-medium">Reconciliation</th>
                                <th class="px-4 py-2 text-left font-medium">SLA Deadline</th>
                                <th class="px-4 py-2 text-center font-medium">Breached</th>
                                <th class="px-4 py-2 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($openExceptions as $exception)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $exception['exception_id'] }}</td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex items-center rounded-md bg-gray-500/10 px-2 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                                            {{ str_replace('_', ' ', ucfirst($exception['type'])) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium
                                            @if(in_array($exception['severity'], ['critical','high'])) bg-danger-500/10 text-danger-700 dark:text-danger-400
                                            @elseif($exception['severity'] === 'medium') bg-warning-500/10 text-warning-700 dark:text-warning-400
                                            @else bg-gray-500/10 text-gray-700 dark:text-gray-300 @endif">
                                            {{ ucfirst($exception['severity']) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium
                                            @if($exception['status'] === 'open') bg-danger-500/10 text-danger-700 dark:text-danger-400
                                            @elseif($exception['status'] === 'under_investigation') bg-warning-500/10 text-warning-700 dark:text-warning-400
                                            @else bg-success-500/10 text-success-700 dark:text-success-400 @endif">
                                            {{ str_replace('_', ' ', ucfirst($exception['status'])) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        @if($exception['related_date'])
                                            {{ $exception['related_date'] }} ({{ ucfirst($exception['related_type']) }})
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">{{ $exception['sla_deadline'] ?? '—' }}</td>
                                    <td class="px-4 py-2 text-center">
                                        @if($exception['sla_breached'])
                                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-danger-500/10 text-danger-600 dark:text-danger-400 text-xs font-bold">!</span>
                                        @else
                                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-success-500/10 text-success-600 dark:text-success-400 text-xs">✓</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <a href="{{ $exception['view_url'] }}" class="text-xs text-primary-600 hover:underline">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

    </div>
</x-filament-panels::page>
