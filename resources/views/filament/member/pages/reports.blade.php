<x-filament-panels::page>
    @php
        $member = $this->getMember();
        $summary = $this->getStatementSummary();
    @endphp

    @if (! $member)
        <x-filament::section>
            <x-slot name="heading">No member profile</x-slot>
            <x-slot name="description">This account is not linked to a member. Please contact an administrator.</x-slot>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">Monthly statement</x-slot>
            <x-slot name="description">Summary statement for {{ $this->getPeriodLabel() }}.</x-slot>

            <div class="flex flex-wrap items-end gap-4 mb-4">
                <div>
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
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Year</label>
                    <select
                        wire:model.live="year"
                        class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:ring-primary-500 focus:border-primary-500 text-sm"
                    >
                        @foreach(range(now()->year, now()->year - 5, -1) as $y)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Bank account</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Closing balance</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        ${{ number_format($summary['closing_bank'], 2) }}
                    </p>
                </div>
                <div class="space-y-2">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Fund account</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Closing balance</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        ${{ number_format($summary['closing_fund'], 2) }}
                    </p>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Contributions this month</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-gray-100">
                        ${{ number_format($summary['contributions'], 2) }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Loan repayments this month</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-gray-100">
                        ${{ number_format($summary['repayments'], 2) }}
                    </p>
                </div>
            </div>

            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                This is a quick on-screen summary. A full downloadable statement/export can be added later.
            </p>
        </x-filament::section>
    @endif
</x-filament-panels::page>

