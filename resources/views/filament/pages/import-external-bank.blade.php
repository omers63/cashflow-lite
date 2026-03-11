<x-filament-panels::page>
    {{-- Poll so Summary (Master Bank Balance, etc.) updates in real time --}}
    <div wire:poll.10s class="contents">
    {{-- ── Import form (includes Summary tally as read-only Filament fields) ── --}}
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
                            <x-filament::icon icon="heroicon-m-check-circle" class="w-4 h-4"/>
                            {{ $newCount }} new
                        </span>
                        <span class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400 font-medium">
                            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="w-4 h-4"/>
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
                                            <x-filament::icon icon="heroicon-m-exclamation-circle" class="w-3 h-3"/> Duplicate
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                                            <x-filament::icon icon="heroicon-m-check-circle" class="w-3 h-3"/> New
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
        <div class="flex items-center gap-3 mt-8 pt-4">
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
    </div>

</x-filament-panels::page>
