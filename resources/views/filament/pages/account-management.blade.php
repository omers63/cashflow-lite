<x-filament-panels::page>

    {{-- Master Accounts Summary --}}
    <x-filament::section
        class="mt-2 rounded-2xl border border-slate-100/80 bg-gradient-to-br from-sky-50 via-indigo-50 to-purple-50 dark:from-slate-900 dark:via-slate-950 dark:to-slate-900 shadow-sm"
    >
        <x-slot name="heading">Master Accounts</x-slot>
        <x-slot name="description">
            Master Bank aggregates external bank imports. Master Fund tracks user contributions and loan activity.
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @php $masterBank = $this->masterBankSummary; @endphp
            @if ($masterBank)
                <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 hover:ring-primary-500 dark:hover:ring-primary-500 transition-colors">
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-2">
                            <div class="flex items-center gap-x-2">
                                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Master Bank Account</span>
                            </div>
                            <div class="text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
                                ${{ number_format($masterBank['balance'], 2) }}
                            </div>
                            <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                @if (! empty($masterBank['balance_date']))
                                    <span>As of {{ $masterBank['balance_date'] }}</span>
                                @endif
                                @if (! empty($masterBank['transaction_count']))
                                    <span>• {{ number_format($masterBank['transaction_count']) }} transactions</span>
                                @endif
                                @if (! empty($masterBank['last_transaction_date']))
                                    <span>• Last: {{ \Carbon\Carbon::parse($masterBank['last_transaction_date'])->format('M d, Y') }}</span>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-2 pt-2">
                                <x-filament::button
                                    size="xs"
                                    color="primary"
                                    tag="a"
                                    href="{{ \App\Filament\Resources\MasterAccountResource::getUrl('view', ['record' => $masterBank['id']]) }}"
                                >
                                    View &amp; manage
                                </x-filament::button>
                                <x-filament::button
                                    size="xs"
                                    color="gray"
                                    tag="a"
                                    href="{{ \App\Filament\Resources\MasterAccountResource::getUrl('edit', ['record' => $masterBank['id']]) }}"
                                >
                                    Adjust balance
                                </x-filament::button>
                            </div>
                        </div>
                        <div class="shrink-0 text-primary-500">
                            <x-filament::icon icon="heroicon-o-banknotes" class="h-10 w-10" />
                        </div>
                    </div>
                </div>
            @endif

            @php $masterFund = $this->masterFundSummary; @endphp
            @if ($masterFund)
                <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white/90 dark:bg-gray-900/90 backdrop-blur-sm p-6 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 hover:ring-success-500 dark:hover:ring-success-500 transition-colors">
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-2">
                            <div class="flex items-center gap-x-2">
                                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Master Fund Account</span>
                            </div>
                            <div class="text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
                                ${{ number_format($masterFund['balance'], 2) }}
                            </div>
                            <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                @if (! empty($masterFund['balance_date']))
                                    <span>As of {{ $masterFund['balance_date'] }}</span>
                                @endif
                                @if (! empty($masterFund['transaction_count']))
                                    <span>• {{ number_format($masterFund['transaction_count']) }} transactions</span>
                                @endif
                                @if (! empty($masterFund['last_transaction_date']))
                                    <span>• Last: {{ \Carbon\Carbon::parse($masterFund['last_transaction_date'])->format('M d, Y') }}</span>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-2 pt-2">
                                <x-filament::button
                                    size="xs"
                                    color="success"
                                    tag="a"
                                    href="{{ \App\Filament\Resources\MasterAccountResource::getUrl('view', ['record' => $masterFund['id']]) }}"
                                >
                                    View &amp; manage
                                </x-filament::button>
                                <x-filament::button
                                    size="xs"
                                    color="gray"
                                    tag="a"
                                    href="{{ \App\Filament\Resources\MasterAccountResource::getUrl('edit', ['record' => $masterFund['id']]) }}"
                                >
                                    Adjust balance
                                </x-filament::button>
                            </div>
                        </div>
                        <div class="shrink-0 text-success-500">
                            <x-filament::icon icon="heroicon-o-wallet" class="h-10 w-10" />
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>

    {{-- External Bank Accounts --}}
    <x-filament::section
        class="mt-6 rounded-2xl border border-slate-100/80 bg-white/80 dark:bg-slate-950/80 backdrop-blur-sm shadow-sm"
    >
        <x-slot name="heading">External Bank Accounts</x-slot>
        <x-slot name="description">
            Manage all linked external bank accounts, review their balances, and jump into imports or detailed views.
        </x-slot>

        <livewire:external-banks-table />
    </x-filament::section>

    {{-- Members Bank & Fund Accounts --}}
    <x-filament::section
        class="mt-6 rounded-2xl border border-slate-100/80 bg-white/80 dark:bg-slate-950/80 backdrop-blur-sm shadow-sm"
    >
        <x-slot name="heading">Member Account Balances</x-slot>
        <x-slot name="description">
            Overview of each member’s bank and fund balances, outstanding loans, and available room to borrow.
        </x-slot>

        {{ $this->table }}
    </x-filament::section>

</x-filament-panels::page>
