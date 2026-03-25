<x-filament-panels::page>
    <div class="space-y-6" wire:poll.30s>
        {{-- Period selector --}}
        <x-filament::section>
            <x-slot name="heading">Period</x-slot>
            <x-slot name="description">
                Select the obligation month. The due date uses <strong>Collections due day</strong> in Settings (default: 5th of the following month). Actual posts dated after that day are flagged <strong>Late</strong> for that month on member transactions, the transactions list, loan repayments, and the dashboard recent activity table.
            </x-slot>
            <div class="flex flex-wrap items-end gap-4">
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Month</label>
                    <select
                        wire:model.live="month"
                        class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:ring-primary-500 focus:border-primary-500 text-sm"
                    >
                        @foreach(range(1, 12) as $m)
                            <option value="{{ $m }}">{{ \Carbon\Carbon::create(2000, $m, 1)->format('F') }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Year</label>
                    <select
                        wire:model.live="year"
                        class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:ring-primary-500 focus:border-primary-500 text-sm"
                    >
                        @foreach(range(now()->year, now()->year - 3, -1) as $y)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <strong>Due date:</strong> {{ $this->getDueDate()?->format('F j, Y') }}
                </div>
            </div>
        </x-filament::section>

        {{-- Stats --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <x-filament::section class="p-4!">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-danger-500/10 text-danger-600 dark:text-danger-400">
                        <x-filament::icon icon="heroicon-o-banknotes" class="w-6 h-6" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Unrealized collections</p>
                        <p class="text-2xl font-bold">{{ $this->getUnrealizedCount() }} <span class="text-sm font-normal text-gray-500">members</span></p>
                        <p class="text-sm font-semibold text-danger-600 dark:text-danger-400">${{ number_format($this->getTotalShortfall(), 2) }} shortfall</p>
                    </div>
                </div>
            </x-filament::section>
            <x-filament::section class="p-4!">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-warning-500/10 text-warning-600 dark:text-warning-400">
                        <x-filament::icon icon="heroicon-o-user-group" class="w-6 h-6" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Unallocated dependants</p>
                        <p class="text-2xl font-bold">{{ $this->getUnallocatedCount() }} <span class="text-sm font-normal text-gray-500">members</span></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">No allocation from parent this month</p>
                    </div>
                </div>
            </x-filament::section>
            <x-filament::section class="p-4!">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary-500/10 text-primary-600 dark:text-primary-400">
                        <x-filament::icon icon="heroicon-o-calendar-days" class="w-6 h-6" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Period</p>
                        <p class="text-xl font-bold">{{ $this->getPeriodLabel() }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Run allocations first, then collections</p>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- Projected Master Fund --}}
        @php $projection = $this->getMasterFundProjection(); @endphp
        <x-filament::section>
            <x-slot name="heading">Projected Master Fund balance</x-slot>
            <x-slot name="description">If contributions and loan repayments for {{ $projection['period_label'] }} are run, and all pending loans in the queue are disbursed.</x-slot>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Current balance</span>
                        <span class="font-semibold">${{ number_format($projection['current_balance'], 2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">+ Projected contributions ({{ $projection['period_label'] }})</span>
                        <span class="font-semibold text-success-600 dark:text-success-400">${{ number_format($projection['projected_contributions'], 2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">+ Projected loan repayments</span>
                        <span class="font-semibold text-success-600 dark:text-success-400">${{ number_format($projection['projected_repayments'], 2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">− Pending disbursements (queue)</span>
                        <span class="font-semibold text-danger-600 dark:text-danger-400">−${{ number_format($projection['pending_disbursements'], 2) }}</span>
                    </div>
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-3 flex justify-between">
                        <span class="font-semibold text-gray-900 dark:text-gray-100">Projected balance</span>
                        <span class="font-bold text-lg {{ $projection['projected_balance'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                            ${{ number_format($projection['projected_balance'], 2) }}
                        </span>
                    </div>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                        <strong>Loan queue</strong> ({{ $projection['loan_queue_count'] }} pending): qualified and pending/unapproved loans ordered by loan tier and submission date. Disbursing all would debit the master fund by ${{ number_format($projection['pending_disbursements'], 2) }}.
                    </p>
                </div>
            </div>
        </x-filament::section>

        {{-- When will we reach a target balance? --}}
        <x-filament::section>
            <x-slot name="heading">When will the master fund reach a target balance?</x-slot>
            <x-slot name="description">Enter a target amount. The system will project when you will reach it based on monthly contributions, loan repayments, and pending loan disbursements (queue).</x-slot>
            <div class="space-y-4">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Target balance ($)</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            wire:model.live="targetBalanceAmount"
                            placeholder="e.g. 50000"
                            class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:ring-primary-500 focus:border-primary-500 text-sm w-48"
                        />
                    </div>
                </div>
                @php $targetResult = $this->getTargetReachResult(); @endphp
                @if($targetResult !== null)
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-4 space-y-3">
                        @if($targetResult['reached'])
                            <p class="font-semibold text-success-600 dark:text-success-400">
                                You will reach <strong>${{ number_format($targetResult['target_amount'], 2) }}</strong> in <strong>{{ $targetResult['reach_period_label'] }}</strong>
                                @if($targetResult['months_to_reach'] > 0)
                                    (in {{ $targetResult['months_to_reach'] }} {{ $targetResult['months_to_reach'] === 1 ? 'month' : 'months' }}).
                                @else
                                    (current balance already meets or exceeds target).
                                @endif
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Projected balance at that time: <strong>${{ number_format($targetResult['balance_at_reach'], 2) }}</strong>
                            </p>
                        @else
                            <p class="font-semibold text-warning-600 dark:text-warning-400">
                                Target <strong>${{ number_format($targetResult['target_amount'], 2) }}</strong> not reached within {{ $targetResult['months_projected'] }} months.
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Projected balance after {{ $targetResult['months_projected'] }} months: <strong>${{ number_format($targetResult['final_balance'], 2) }}</strong>
                            </p>
                        @endif
                        <div class="text-sm border-t border-gray-200 dark:border-gray-700 pt-3">
                            <p class="font-medium text-gray-700 dark:text-gray-300 mb-1">Breakdown (contributions, repayments, disbursements):</p>
                            <ul class="space-y-1 text-gray-600 dark:text-gray-400">
                                <li>+ Contributions: <span class="font-medium text-success-600 dark:text-success-400">${{ number_format($targetResult['total_contributions'], 2) }}</span></li>
                                <li>+ Loan repayments: <span class="font-medium text-success-600 dark:text-success-400">${{ number_format($targetResult['total_repayments'], 2) }}</span></li>
                                <li>− Loan disbursements: <span class="font-medium text-danger-600 dark:text-danger-400">${{ number_format($targetResult['total_disbursements'], 2) }}</span></li>
                            </ul>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
                                Assumes {{ number_format($targetResult['monthly_contributions'], 2) }}/mo contributions and {{ number_format($targetResult['monthly_repayments'], 2) }}/mo repayments; pending queue ({{ number_format($targetResult['first_period_disbursements'], 2) }}) applied in the first period.
                            </p>
                        </div>
                    </div>
                @endif
            </div>
        </x-filament::section>

        {{-- Loan queue table --}}
        @if($projection['loan_queue_count'] > 0)
            <x-filament::section>
                <x-slot name="heading">Loan queue (pending / unapproved)</x-slot>
                <x-slot name="description">Ordered by loan tier (amount band) then submission date. These disbursements are included in the projected master fund above.</x-slot>
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Loan ID</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Member</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Amount</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Submitted</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                            @foreach($projection['loan_queue'] as $loan)
                                <tr>
                                    <td class="px-4 py-3 font-mono">{{ $loan->loan_id }}</td>
                                    <td class="px-4 py-3">{{ $loan->user?->name ?? "Member #{$loan->member_id}" }}</td>
                                    <td class="px-4 py-3 text-right font-medium">${{ number_format((float) $loan->original_amount, 2) }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $loan->created_at?->format('M d, Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        {{-- Unrealized collections --}}
        <x-filament::section>
            <x-slot name="heading">Unrealized collections ({{ $this->getPeriodLabel() }})</x-slot>
            <x-slot name="description">Members with expected contribution and/or loan repayment not yet fully recorded for this month.</x-slot>
            @php $unrealized = $this->getUnrealized(); @endphp
            @if(empty($unrealized))
                <div class="flex items-center gap-3 py-6 text-success-600 dark:text-success-400">
                    <x-filament::icon icon="heroicon-o-check-circle" class="w-8 h-8" />
                    <span class="font-medium">All members are up to date for this period.</span>
                </div>
            @else
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Member</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Expected contribution</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Expected repayment</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Realized</th>
                                <th class="px-4 py-3 text-right font-semibold text-danger-600 dark:text-danger-400">Shortfall</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                            @foreach($unrealized as $row)
                                @php $member = $row['member']; @endphp
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                        {{ $member->user?->name ?? "Member #{$member->id}" }}
                                        @if($member->user?->user_code)
                                            <span class="text-gray-500 text-xs">({{ $member->user->user_code }})</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">${{ number_format($row['expected_contribution'], 2) }}</td>
                                    <td class="px-4 py-3 text-right">${{ number_format($row['expected_repayment'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">
                                        ${{ number_format($row['realized_contribution'] + $row['realized_repayment'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-danger-600 dark:text-danger-400">
                                        ${{ number_format($row['shortfall'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ \App\Filament\Resources\MemberResource::getUrl('view', ['record' => $member]) }}" class="text-primary-600 hover:underline text-sm">View member</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        {{-- Unallocated dependants --}}
        <x-filament::section>
            <x-slot name="heading">Unallocated dependants ({{ $this->getPeriodLabel() }})</x-slot>
            <x-slot name="description">Dependants who have not received an allocation from their parent this month. Run allocations before contributions so dependants have bank balance.</x-slot>
            @php $unallocated = $this->getUnallocated(); @endphp
            @if(empty($unallocated))
                <div class="flex items-center gap-3 py-6 text-success-600 dark:text-success-400">
                    <x-filament::icon icon="heroicon-o-check-circle" class="w-8 h-8" />
                    <span class="font-medium">All dependants have been allocated for this period.</span>
                </div>
            @else
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Dependant</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Parent</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Allowed allocation</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                            @foreach($unallocated as $row)
                                @php $dependant = $row['dependant']; $parent = $row['parent']; @endphp
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                        {{ $dependant->user?->name ?? "Member #{$dependant->id}" }}
                                        @if($dependant->user?->user_code)
                                            <span class="text-gray-500 text-xs">({{ $dependant->user->user_code }})</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                        {{ $parent->user?->name ?? "Member #{$parent->id}" }}
                                    </td>
                                    <td class="px-4 py-3 text-right">${{ number_format($row['allowed_allocation'], 2) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ \App\Filament\Resources\MemberResource::getUrl('view', ['record' => $parent]) }}" class="text-primary-600 hover:underline text-sm">View parent</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        {{-- Workflow hint --}}
        <x-filament::section class="bg-gray-50 dark:bg-gray-800/50">
            <x-slot name="heading">Workflow</x-slot>
            <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600 dark:text-gray-400">
                <li>Select the <strong>period (month/year)</strong> above.</li>
                <li>Review <strong>unallocated dependants</strong> and <strong>unrealized collections</strong>.</li>
                <li>Click <strong>Run allocations for this month</strong> to create parent→dependant allocations (so dependants have bank balance).</li>
                <li>Click <strong>Run contributions & repayments</strong> to post contributions and loan repayments for all members with shortfall. Members must have sufficient bank balance.</li>
            </ol>
        </x-filament::section>
    </div>
</x-filament-panels::page>
