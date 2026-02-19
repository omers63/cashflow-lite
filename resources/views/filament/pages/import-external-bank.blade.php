<x-filament-panels::page>

    {{-- ── Modern Stats Cards ──────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Master Bank Balance --}}
        <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 p-6 shadow-lg hover:shadow-xl transition-shadow duration-300">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-blue-100 mb-1">Master Bank Balance</p>
                    <p class="text-3xl font-bold text-white tracking-tight">{{ $this->masterBankBalance }}</p>
                </div>
                <div class="flex-shrink-0">
                    <div class="p-3 bg-white/20 rounded-lg backdrop-blur-sm">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="absolute bottom-0 right-0 -mr-6 -mb-6 opacity-10">
                <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                </svg>
            </div>
        </div>

        {{-- Imported Today --}}
        <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-6 shadow-sm hover:shadow-md transition-all duration-300 hover:border-green-300 dark:hover:border-green-700">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Imported Today</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">{{ $this->todayImportCount }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">transactions processed</p>
                </div>
                <div class="flex-shrink-0">
                    <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- Duplicates Today --}}
        <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-6 shadow-sm hover:shadow-md transition-all duration-300 hover:border-amber-300 dark:hover:border-amber-700">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Duplicates Today</p>
                    <p class="text-3xl font-bold text-amber-600 dark:text-amber-400 tracking-tight">{{ $this->todayDuplicateCount }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">already in system</p>
                </div>
                <div class="flex-shrink-0">
                    <div class="p-3 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                        <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- Total Amount Today --}}
        <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 p-6 shadow-lg hover:shadow-xl transition-shadow duration-300">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <p class="text-sm font-medium text-emerald-100 mb-1">Total Amount Today</p>
                    <p class="text-3xl font-bold text-white tracking-tight">{{ $this->todayTotalAmount }}</p>
                    <p class="text-xs text-emerald-100 mt-2">successfully imported</p>
                </div>
                <div class="flex-shrink-0">
                    <div class="p-3 bg-white/20 rounded-lg backdrop-blur-sm">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="absolute bottom-0 right-0 -mr-8 -mb-8 opacity-10">
                <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>

    {{-- ── Import form ─────────────────────────────────────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">New Import</x-slot>
        <x-slot name="description">Select a bank account and enter transaction details, or upload a CSV file.</x-slot>

        {{ $this->form }}

        {{-- Preview panel --}}
        @if ($showPreview && count($previewRows))
            <div class="mt-6 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Import Preview</h3>
                    <div class="flex gap-3 text-xs">
                        <span class="inline-flex items-center gap-1 text-green-700 dark:text-green-400 font-medium">
                            <x-heroicon-m-check-circle class="w-4 h-4"/>
                            {{ $newCount }} new
                        </span>
                        <span class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400 font-medium">
                            <x-heroicon-m-exclamation-triangle class="w-4 h-4"/>
                            {{ $duplicateCount }} duplicate
                        </span>
                    </div>
                </div>
                <table class="w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            @foreach (['Bank', 'Txn Date', 'Reference', 'Amount', 'Description', 'Status'] as $col)
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($previewRows as $row)
                            <tr class="{{ $row['is_duplicate'] ? 'bg-amber-50 dark:bg-amber-900/20' : '' }}">
                                <td class="px-4 py-2 font-medium text-gray-800 dark:text-gray-200">{{ $row['bank_name'] }}</td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ \Carbon\Carbon::parse($row['transaction_date'])->format('M d, Y') }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $row['external_ref_id'] }}</td>
                                <td class="px-4 py-2 font-semibold text-gray-800 dark:text-gray-200">${{ number_format($row['amount'], 2) }}</td>
                                <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ $row['description'] ?: '—' }}</td>
                                <td class="px-4 py-2">
                                    @if ($row['is_duplicate'])
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                            <x-heroicon-m-exclamation-circle class="w-3 h-3"/> Duplicate
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                                            <x-heroicon-m-check-circle class="w-3 h-3"/> New
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Action buttons --}}
        <div class="flex items-center gap-3 mt-6">
            <x-filament::button
                wire:click="previewImport"
                color="info"
                icon="heroicon-m-eye"
            >
                Preview Import
            </x-filament::button>

            @if ($showPreview && $newCount > 0)
                <x-filament::button
                    wire:click="processImport"
                    color="success"
                    icon="heroicon-m-arrow-down-tray"
                    wire:confirm="Import {{ $newCount }} transaction(s)? {{ $duplicateCount }} duplicate(s) will be skipped."
                >
                    Confirm &amp; Import ({{ $newCount }})
                </x-filament::button>
            @endif

            @if ($showPreview)
                <x-filament::button
                    wire:click="$set('showPreview', false)"
                    color="gray"
                    icon="heroicon-m-x-mark"
                >
                    Clear Preview
                </x-filament::button>
            @endif
        </div>
    </x-filament::section>

    {{-- ── Recent imports table ────────────────────────────────────────── --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">Import History</x-slot>
        <x-slot name="description">All imported transactions. Use bulk actions to re-process pending items.</x-slot>

        {{ $this->table }}
    </x-filament::section>

</x-filament-panels::page>
