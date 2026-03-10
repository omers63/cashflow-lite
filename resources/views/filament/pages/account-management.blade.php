<x-filament-panels::page>

    {{-- Master Accounts Summary --}}
    <x-filament::section>
        <x-slot name="heading">Master Accounts</x-slot>
        <x-slot name="description">Master Bank aggregates external bank imports. Master Fund tracks user contributions minus outstanding loans.</x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @php $masterBank = $this->masterBankSummary; @endphp
            @if ($masterBank)
                <a href="{{ \App\Filament\Resources\MasterAccountResource::getUrl('edit', ['record' => $masterBank['id']]) }}"
                   class="block p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-primary-500 dark:hover:border-primary-500 transition-colors">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Master Bank Account</p>
                            <p class="text-2xl font-bold">${{ number_format($masterBank['balance'], 2) }}</p>
                            <p class="text-xs text-gray-500 mt-1">As of {{ $masterBank['balance_date'] }}</p>
                        </div>
                        <x-filament::icon icon="heroicon-m-banknotes" class="h-10 w-10 text-primary-500" />
                    </div>
                </a>
            @endif

            @php $masterFund = $this->masterFundSummary; @endphp
            @if ($masterFund)
                <a href="{{ \App\Filament\Resources\MasterAccountResource::getUrl('edit', ['record' => $masterFund['id']]) }}"
                   class="block p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-success-500 dark:hover:border-success-500 transition-colors">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Master Fund Account</p>
                            <p class="text-2xl font-bold">${{ number_format($masterFund['balance'], 2) }}</p>
                            <p class="text-xs text-gray-500 mt-1">As of {{ $masterFund['balance_date'] }}</p>
                        </div>
                        <x-filament::icon icon="heroicon-m-wallet" class="h-10 w-10 text-success-500" />
                    </div>
                </a>
            @endif
        </div>
    </x-filament::section>

    {{-- Members Bank & Fund Accounts --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">Members Bank Accounts & Fund Accounts</x-slot>
        <x-slot name="description">Member Bank Accounts receive distributions from Master Bank. Member Fund Accounts track contributions and loan repayments.</x-slot>

        {{ $this->table }}
    </x-filament::section>

</x-filament-panels::page>
