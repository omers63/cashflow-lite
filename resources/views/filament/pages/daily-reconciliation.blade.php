<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Latest Reconciliation -->
        @if($latestReconciliation && $latestReconciliation['latest'])
            <x-filament::section>
                <x-slot name="heading">
                    Latest Reconciliation
                </x-slot>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h4 class="font-semibold mb-2">Date: {{ \Carbon\Carbon::parse($latestReconciliation['latest']->reconciliation_date)->format('M d, Y') }}</h4>
                        <p class="text-sm">Status: 
                            <span class="@if($latestReconciliation['latest']->all_passed) text-success-600 @else text-danger-600 @endif font-semibold">
                                {{ $latestReconciliation['latest']->all_passed ? 'PASSED' : 'FAILED' }}
                            </span>
                        </p>
                        <p class="text-sm">Checks Passed: {{ $latestReconciliation['latest']->checks_passed }}/7</p>
                        <p class="text-sm">Checks Failed: {{ $latestReconciliation['latest']->checks_failed }}</p>
                    </div>
                    <div>
                        <h4 class="font-semibold mb-2">This Month</h4>
                        <p class="text-sm">Total Reconciliations: {{ $latestReconciliation['month_total'] }}</p>
                        <p class="text-sm">Passed: {{ $latestReconciliation['month_passed'] }}</p>
                        <p class="text-sm">Failed: {{ $latestReconciliation['month_failed'] }}</p>
                        <p class="text-sm">Pass Rate: {{ $latestReconciliation['month_pass_rate'] }}%</p>
                    </div>
                </div>
            </x-filament::section>
        @endif

        <!-- System Totals -->
        @if($systemTotals)
            <x-filament::section>
                <x-slot name="heading">
                    Current System Totals
                </x-slot>

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
    </div>
</x-filament-panels::page>
