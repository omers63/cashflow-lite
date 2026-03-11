<x-filament-panels::page>

    {{-- Master Accounts Summary --}}
    <x-filament::section>
        <x-slot name="heading">Master Accounts</x-slot>
        <x-slot name="description">Master Bank aggregates external bank imports. Master Fund tracks user contributions minus outstanding loans.</x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @php $masterBank = $this->masterBankSummary; @endphp
            @if ($masterBank)
                <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 hover:ring-primary-500 dark:hover:ring-primary-500 transition-colors">
                    <a href="{{ \App\Filament\Resources\MasterAccountResource::getUrl('edit', ['record' => $masterBank['id']]) }}" class="absolute inset-0 z-10 rounded-xl"></a>
                    <div class="grid gap-y-2">
                        <div class="flex items-center gap-x-2">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Master Bank Account</span>
                        </div>
                        <div class="text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
                            ${{ number_format($masterBank['balance'], 2) }}
                        </div>
                        <div class="flex items-center gap-x-2">
                            <span class="text-sm text-gray-500 dark:text-gray-400">As of {{ $masterBank['balance_date'] }}</span>
                        </div>
                    </div>
                    <div class="absolute top-6 right-6 text-primary-500">
                        <x-filament::icon icon="heroicon-o-banknotes" class="h-8 w-8" />
                    </div>
                </div>
            @endif

            @php $masterFund = $this->masterFundSummary; @endphp
            @if ($masterFund)
                <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 hover:ring-success-500 dark:hover:ring-success-500 transition-colors">
                    <a href="{{ \App\Filament\Resources\MasterAccountResource::getUrl('edit', ['record' => $masterFund['id']]) }}" class="absolute inset-0 z-10 rounded-xl"></a>
                    <div class="grid gap-y-2">
                        <div class="flex items-center gap-x-2">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Master Fund Account</span>
                        </div>
                        <div class="text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
                            ${{ number_format($masterFund['balance'], 2) }}
                        </div>
                        <div class="flex items-center gap-x-2">
                            <span class="text-sm text-gray-500 dark:text-gray-400">As of {{ $masterFund['balance_date'] }}</span>
                        </div>
                    </div>
                    <div class="absolute top-6 right-6 text-success-500">
                        <x-filament::icon icon="heroicon-o-wallet" class="h-8 w-8" />
                    </div>
                </div>
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
