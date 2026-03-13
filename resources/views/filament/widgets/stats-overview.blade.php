<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Overview
        </x-slot>

        <x-slot name="description">
            Key cashflow system statistics.
        </x-slot>

        @php
            $summary = $this->getSummary();
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3">
            {{-- Master Bank Balance --}}
            <a href="{{ $this->getAccountManagementUrl() }}"
               class="flex flex-col justify-between rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-3 hover:border-primary-500 hover:bg-primary-50/40 dark:hover:border-primary-500 transition-colors">
                <div class="flex items-center justify-between mb-1">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Master Bank Balance
                    </div>
                    <x-filament::icon icon="heroicon-m-banknotes" class="w-5 h-5 text-primary-500" />
                </div>
                <div class="text-lg font-semibold">
                    ${{ number_format($summary['bank_balance'], 2) }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Total system bank funds
                </div>
            </a>

            {{-- Master Fund Balance --}}
            <a href="{{ $this->getAccountManagementUrl() }}"
               class="flex flex-col justify-between rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-3 hover:border-primary-500 hover:bg-primary-50/40 dark:hover:border-primary-500 transition-colors">
                <div class="flex items-center justify-between mb-1">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Master Fund Balance
                    </div>
                    <x-filament::icon icon="heroicon-m-building-library" class="w-5 h-5 text-primary-500" />
                </div>
                <div class="text-lg font-semibold">
                    ${{ number_format($summary['fund_balance'], 2) }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Available for member loans
                </div>
            </a>

            {{-- Active Members --}}
            <a href="{{ $this->getMembersUrl() }}"
               class="flex flex-col justify-between rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-3 hover:border-primary-500 hover:bg-primary-50/40 dark:hover:border-primary-500 transition-colors">
                <div class="flex items-center justify-between mb-1">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Active Members
                    </div>
                    <x-filament::icon icon="heroicon-m-user-group" class="w-5 h-5 text-primary-500" />
                </div>
                <div class="text-lg font-semibold">
                    {{ number_format($summary['total_members']) }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Total registered members
                </div>
            </a>

            {{-- Active Loans --}}
            <a href="{{ $this->getLoansUrl() }}"
               class="flex flex-col justify-between rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-3 hover:border-primary-500 hover:bg-primary-50/40 dark:hover:border-primary-500 transition-colors">
                <div class="flex items-center justify-between mb-1">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Active Loans
                    </div>
                    <x-filament::icon icon="heroicon-m-currency-dollar" class="w-5 h-5 text-primary-500" />
                </div>
                <div class="text-lg font-semibold">
                    {{ number_format($summary['active_loans']) }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    ${{ number_format($summary['loan_outstanding'], 2) }} outstanding
                </div>
            </a>

            {{-- Open Exceptions --}}
            <a href="{{ $this->getExceptionsUrl() }}"
               class="flex flex-col justify-between rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-3 hover:border-primary-500 hover:bg-primary-50/40 dark:hover:border-primary-500 transition-colors">
                <div class="flex items-center justify-between mb-1">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Open Exceptions
                    </div>
                    <x-filament::icon
                        :icon="$summary['open_exceptions'] > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle'"
                        :class="$summary['open_exceptions'] > 0 ? 'w-5 h-5 text-danger-500' : 'w-5 h-5 text-success-500'"
                    />
                </div>
                <div class="text-lg font-semibold">
                    {{ number_format($summary['open_exceptions']) }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    {{ number_format($summary['overdue_exceptions']) }} overdue
                </div>
            </a>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

