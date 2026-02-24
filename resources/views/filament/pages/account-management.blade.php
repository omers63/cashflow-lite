<x-filament-panels::page>

    {{-- Master Accounts Summary --}}
    <x-filament::section>
        <x-slot name="heading">Master Accounts</x-slot>
        <x-slot name="description">Master Bank aggregates external bank imports. Master Fund tracks user contributions minus outstanding loans.</x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @php $masterBank = $this->masterBank; @endphp
            @if ($masterBank)
                <a href="{{ \App\Filament\Resources\MasterAccountResource::getUrl('edit', ['record' => $masterBank]) }}"
                   class="block p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-primary-500 dark:hover:border-primary-500 transition-colors">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Master Bank Account</p>
                            <p class="text-2xl font-bold">${{ number_format($masterBank->balance, 2) }}</p>
                            <p class="text-xs text-gray-500 mt-1">As of {{ $masterBank->balance_date?->format('M d, Y') }}</p>
                        </div>
                        <span class="inline-flex size-6 shrink-0 items-center justify-center overflow-hidden text-primary-500 [&>svg]:!size-6">
                            <x-heroicon-m-banknotes />
                        </span>
                    </div>
                </a>
            @endif

            @php $masterFund = $this->masterFund; @endphp
            @if ($masterFund)
                <a href="{{ \App\Filament\Resources\MasterAccountResource::getUrl('edit', ['record' => $masterFund]) }}"
                   class="block p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-success-500 dark:hover:border-success-500 transition-colors">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Master Fund Account</p>
                            <p class="text-2xl font-bold">${{ number_format($masterFund->balance, 2) }}</p>
                            <p class="text-xs text-gray-500 mt-1">As of {{ $masterFund->balance_date?->format('M d, Y') }}</p>
                        </div>
                        <span class="inline-flex size-6 shrink-0 items-center justify-center overflow-hidden text-success-500 [&>svg]:!size-6">
                            <x-heroicon-m-wallet />
                        </span>
                    </div>
                </a>
            @endif
        </div>
    </x-filament::section>

    {{-- Users Bank & Fund Accounts --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">Users Bank Accounts & Fund Accounts</x-slot>
        <x-slot name="description">User Bank Accounts receive distributions from Master Bank. User Fund Accounts track contributions and loan repayments.</x-slot>

        {{ $this->table }}
    </x-filament::section>

</x-filament-panels::page>
