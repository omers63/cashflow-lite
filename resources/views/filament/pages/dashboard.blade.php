<x-filament-panels::page>
    @php
        $summary = $this->getSummary();
    @endphp

    {{-- ─── HERO BANNER ──────────────────────────────────────────────────────── --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-violet-600 via-purple-600 to-indigo-700 dark:from-violet-700 dark:via-purple-700 dark:to-indigo-800 p-6 sm:p-8 mb-8 shadow-lg">
        <div class="absolute -top-12 -right-12 h-48 w-48 rounded-full bg-white/10 blur-3xl pointer-events-none"></div>
        <div class="absolute bottom-0 left-4 h-32 w-32 rounded-full bg-white/5 blur-2xl pointer-events-none"></div>
        <div class="absolute top-1/2 right-1/4 h-20 w-20 rounded-full bg-indigo-400/20 blur-xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-violet-200 text-sm font-medium mb-1">Cashflow System</p>
                <h2 class="text-2xl sm:text-3xl font-bold text-white mb-1">Admin Dashboard</h2>
                <p class="text-violet-200/75 text-sm">{{ now()->format('l, F j, Y') }}</p>
            </div>
            <div class="flex items-center gap-3">
                @if($summary['open_exceptions'] > 0)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-500/20 border border-rose-400/30 px-3 py-1.5 text-xs font-semibold text-rose-100">
                        <span class="h-1.5 w-1.5 rounded-full bg-rose-400 animate-pulse"></span>
                        {{ $summary['open_exceptions'] }} open exception{{ $summary['open_exceptions'] !== 1 ? 's' : '' }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/20 border border-emerald-400/30 px-3 py-1.5 text-xs font-semibold text-emerald-100">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                        All clear
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- ─── STAT CARDS ────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5 mb-8">

        {{-- Master Bank Balance --}}
        <div class="group relative overflow-hidden rounded-2xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-6 shadow-sm transition-all hover:shadow-lg hover:border-emerald-300 dark:hover:border-emerald-700">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-50 to-transparent dark:from-emerald-500/5 dark:to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="absolute top-0 right-0 h-28 w-28 translate-x-8 -translate-y-8 rounded-full bg-emerald-400/10 blur-2xl group-hover:scale-125 transition-transform duration-500"></div>
            <div class="relative">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-teal-500 shadow-sm shadow-emerald-200 dark:shadow-emerald-900/40">
                        <x-filament::icon icon="heroicon-o-building-library" class="h-5 w-5 text-white" />
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Master Bank</p>
                </div>
                <p class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">
                    ${{ number_format($summary['bank_balance'], 2) }}
                </p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1.5">Total system bank funds</p>
            </div>
        </div>

        {{-- Master Fund Balance --}}
        <div class="group relative overflow-hidden rounded-2xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-6 shadow-sm transition-all hover:shadow-lg hover:border-violet-300 dark:hover:border-violet-700">
            <div class="absolute inset-0 bg-gradient-to-br from-violet-50 to-transparent dark:from-violet-500/5 dark:to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="absolute top-0 right-0 h-28 w-28 translate-x-8 -translate-y-8 rounded-full bg-violet-400/10 blur-2xl group-hover:scale-125 transition-transform duration-500"></div>
            <div class="relative">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-indigo-600 shadow-sm shadow-violet-200 dark:shadow-violet-900/40">
                        <x-filament::icon icon="heroicon-o-banknotes" class="h-5 w-5 text-white" />
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Master Fund</p>
                </div>
                <p class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">
                    ${{ number_format($summary['fund_balance'], 2) }}
                </p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1.5">Available for member loans</p>
            </div>
        </div>

        {{-- Active Members --}}
        <div class="group relative overflow-hidden rounded-2xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-6 shadow-sm transition-all hover:shadow-lg hover:border-sky-300 dark:hover:border-sky-700">
            <div class="absolute inset-0 bg-gradient-to-br from-sky-50 to-transparent dark:from-sky-500/5 dark:to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="absolute top-0 right-0 h-28 w-28 translate-x-8 -translate-y-8 rounded-full bg-sky-400/10 blur-2xl group-hover:scale-125 transition-transform duration-500"></div>
            <div class="relative">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-sky-400 to-blue-500 shadow-sm shadow-sky-200 dark:shadow-sky-900/40">
                        <x-filament::icon icon="heroicon-o-user-group" class="h-5 w-5 text-white" />
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Members</p>
                </div>
                <p class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">
                    {{ number_format($summary['total_members']) }}
                </p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1.5">Total registered members</p>
            </div>
        </div>

        {{-- Active Loans --}}
        <div class="group relative overflow-hidden rounded-2xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-6 shadow-sm transition-all hover:shadow-lg hover:border-amber-300 dark:hover:border-amber-700">
            <div class="absolute inset-0 bg-gradient-to-br from-amber-50 to-transparent dark:from-amber-500/5 dark:to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="absolute top-0 right-0 h-28 w-28 translate-x-8 -translate-y-8 rounded-full bg-amber-400/10 blur-2xl group-hover:scale-125 transition-transform duration-500"></div>
            <div class="relative">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 shadow-sm shadow-amber-200 dark:shadow-amber-900/40">
                        <x-filament::icon icon="heroicon-o-currency-dollar" class="h-5 w-5 text-white" />
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Active Loans</p>
                </div>
                <p class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">
                    {{ $summary['active_loans'] }}
                </p>
                <p class="text-xs text-amber-600 dark:text-amber-400 font-medium mt-1.5">
                    ${{ number_format($summary['loan_outstanding'], 2) }} outstanding
                </p>
            </div>
        </div>

        {{-- Open Exceptions --}}
        @php $hasExceptions = $summary['open_exceptions'] > 0; @endphp
        <div class="group relative overflow-hidden rounded-2xl border {{ $hasExceptions ? 'border-rose-200 dark:border-rose-900/50' : 'border-gray-200 dark:border-white/10' }} bg-white dark:bg-gray-900 p-6 shadow-sm transition-all hover:shadow-lg {{ $hasExceptions ? 'hover:border-rose-400' : 'hover:border-emerald-300 dark:hover:border-emerald-700' }}">
            <div class="absolute inset-0 bg-gradient-to-br {{ $hasExceptions ? 'from-rose-50 dark:from-rose-500/5' : 'from-emerald-50 dark:from-emerald-500/5' }} to-transparent {{ $hasExceptions ? 'opacity-60' : 'opacity-0 group-hover:opacity-100' }} transition-opacity"></div>
            <div class="absolute top-0 right-0 h-28 w-28 translate-x-8 -translate-y-8 rounded-full {{ $hasExceptions ? 'bg-rose-400/10' : 'bg-emerald-400/10' }} blur-2xl group-hover:scale-125 transition-transform duration-500"></div>
            <div class="relative">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br {{ $hasExceptions ? 'from-rose-400 to-red-600 shadow-rose-200 dark:shadow-rose-900/40' : 'from-emerald-400 to-green-500 shadow-emerald-200 dark:shadow-emerald-900/40' }} shadow-sm">
                        <x-filament::icon icon="{{ $hasExceptions ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle' }}" class="h-5 w-5 text-white" />
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Exceptions</p>
                </div>
                <p class="text-3xl font-bold {{ $hasExceptions ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-white' }} tracking-tight">
                    {{ $summary['open_exceptions'] }}
                </p>
                <p class="text-xs mt-1.5 {{ $summary['overdue_exceptions'] > 0 ? 'text-rose-500 dark:text-rose-400 font-semibold' : 'text-gray-400 dark:text-gray-500' }}">
                    @if($summary['overdue_exceptions'] > 0)
                        ⚠ {{ $summary['overdue_exceptions'] }} overdue
                    @else
                        No overdue exceptions
                    @endif
                </p>
            </div>
        </div>

        {{-- Fund / Bank Split --}}
        @php
            $bankBal  = (float) $summary['bank_balance'];
            $fundBal  = (float) $summary['fund_balance'];
            $totalBal = $bankBal + $fundBal;
            $fundPct  = $totalBal > 0 ? round(($fundBal / $totalBal) * 100) : 0;
            $bankPct  = $totalBal > 0 ? 100 - $fundPct : 0;
        @endphp
        <div class="group relative overflow-hidden rounded-2xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-6 shadow-sm transition-all hover:shadow-lg hover:border-indigo-300 dark:hover:border-indigo-700">
            <div class="absolute inset-0 bg-linear-to-br from-indigo-50 to-transparent dark:from-indigo-500/5 dark:to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-linear-to-br from-indigo-500 to-purple-600 shadow-sm shadow-indigo-200 dark:shadow-indigo-900/40">
                        <x-filament::icon icon="heroicon-o-chart-pie" class="h-5 w-5 text-white" />
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Fund / Bank Split</p>
                </div>
                <div class="flex h-3 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10 mb-3">
                    <div class="h-full bg-linear-to-r from-violet-500 to-indigo-500 transition-all duration-700" style="width: {{ $fundPct }}%"></div>
                    <div class="h-full bg-linear-to-r from-emerald-400 to-teal-500 transition-all duration-700" style="width: {{ $bankPct }}%"></div>
                </div>
                <div class="flex justify-between text-xs font-medium">
                    <span class="text-violet-600 dark:text-violet-400">Fund {{ $fundPct }}%</span>
                    <span class="text-emerald-600 dark:text-emerald-400">Bank {{ $bankPct }}%</span>
                </div>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Total: ${{ number_format($totalBal, 2) }}</p>
            </div>
        </div>

    </div>

    {{-- ─── FILAMENT WIDGETS ──────────────────────────────────────────────────── --}}
    <x-filament-widgets::widgets
        :widgets="$this->getVisibleWidgets()"
        :columns="$this->getColumns()"
    />

</x-filament-panels::page>
