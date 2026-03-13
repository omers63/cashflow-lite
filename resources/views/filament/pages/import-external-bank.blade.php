<x-filament-panels::page>
    {{-- Poll so Summary (Master Bank Balance, etc.) updates in real time --}}
    <div wire:poll.10s class="contents space-y-6">
    {{-- ── Step 1 & 2: Configure + Preview import ── --}}
    <x-filament::section
        class="rounded-2xl border border-slate-100/80 bg-gradient-to-br from-emerald-50 via-sky-50 to-indigo-50 dark:from-slate-900 dark:via-slate-950 dark:to-slate-900 shadow-sm"
    >
        <x-slot name="heading">
            <span class="flex items-center gap-2">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500 text-xs font-semibold text-white">1</span>
                <span>Import External Bank Transactions</span>
            </span>
        </x-slot>
        <x-slot name="description">
            <span class="block text-xs text-slate-700 dark:text-slate-200">
                Select a bank and import method, then <strong>Confirm &amp; Import</strong>. Each import becomes a session you can review and post to Master.
            </span>
        </x-slot>

        {{ $this->form }}

        {{-- Preview panel --}}
        @if ($showPreview && count($previewRows))
            <div class="mt-6 rounded-xl border border-slate-200/80 dark:border-slate-700/80 overflow-hidden bg-white/80 dark:bg-slate-950/80 backdrop-blur-sm">
                <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-sky-500/10 via-indigo-500/10 to-purple-500/10 dark:from-sky-500/20 dark:via-indigo-500/20 dark:to-purple-500/20 border-b border-slate-200/60 dark:border-slate-700/80">
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Import Preview</h3>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Show:</span>
                            <select
                                wire:model.live="previewTableFilter"
                                class="fi-input block w-auto rounded-lg border-slate-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                            >
                                <option value="all">All ({{ count($previewRows) }})</option>
                                <option value="new">New only ({{ $newCount }})</option>
                                <option value="duplicates">Duplicates only ({{ $duplicateCount }})</option>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                wire:click="selectAllPreview"
                                class="text-xs font-medium text-emerald-700 dark:text-emerald-300 hover:underline"
                            >
                                Select all
                            </button>
                            <span class="text-slate-400 dark:text-slate-500">|</span>
                            <button
                                type="button"
                                wire:click="deselectAllPreview"
                                class="text-xs font-medium text-slate-600 dark:text-slate-300 hover:underline"
                            >
                                Deselect all
                            </button>
                        </div>
                        <div class="flex gap-3 text-xs">
                            <span class="inline-flex items-center gap-1 text-emerald-700 dark:text-emerald-300 font-medium">
                                <x-filament::icon icon="heroicon-m-check-circle" class="w-4 h-4"/>
                                {{ count($previewSelectedIndices) }} selected
                            </span>
                            <span class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-300 font-medium">
                                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="w-4 h-4"/>
                                {{ $duplicateCount }} duplicate
                            </span>
                        </div>
                    </div>
                </div>
                <table class="w-full text-sm divide-y divide-slate-200 dark:divide-slate-800">
                    <thead class="bg-slate-50/80 dark:bg-slate-900/80">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide w-12">Import</th>
                            @foreach (['Bank', 'Txn Date', 'Reference', 'Amount', 'Description', 'Status'] as $col)
                                <th class="px-4 py-2 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="bg-white/80 dark:bg-slate-950/80 divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($this->getFilteredPreviewRows() as $index => $row)
                            <tr class="{{ $row['is_duplicate'] ? 'bg-amber-50/80 dark:bg-amber-900/20' : 'hover:bg-emerald-50/60 dark:hover:bg-emerald-900/10' }}">
                                <td class="px-3 py-2">
                                    <input
                                        type="checkbox"
                                        @checked(in_array($index, $previewSelectedIndices))
                                        wire:click="togglePreviewSelection({{ $index }})"
                                        @if($row['is_duplicate'])
                                            title="Force import duplicate"
                                        @endif
                                        class="fi-checkbox-input h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500 dark:border-slate-600 dark:bg-slate-800 dark:checked:border-primary-500 dark:checked:bg-primary-500"
                                    />
                                </td>
                                <td class="px-4 py-2 font-medium text-slate-900 dark:text-slate-100">{{ $row['bank_name'] }}</td>
                                <td class="px-4 py-2 text-slate-600 dark:text-slate-300">{{ \Carbon\Carbon::parse($row['transaction_date'])->format('M d, Y') }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $row['external_ref_id'] }}</td>
                                <td class="px-4 py-2 font-semibold text-slate-900 dark:text-slate-100">${{ number_format($row['amount'], 2) }}</td>
                                <td class="px-4 py-2 text-slate-500 dark:text-slate-300">{{ $row['description'] ?: '—' }}</td>
                                <td class="px-4 py-2">
                                    @if ($row['is_duplicate'])
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                            <x-filament::icon icon="heroicon-m-exclamation-circle" class="w-3 h-3"/> Duplicate
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
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

        {{-- Action buttons (Manual entry: show Preview link; after preview: Confirm & Import / Clear) --}}
        <div class="flex items-center gap-3 mt-8 pt-4">
            @if (! $showPreview)
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Using <strong>Manual Entry</strong>? <button type="button" wire:click="previewImport" class="font-medium text-primary-600 hover:underline dark:text-primary-400">Preview entries</button> before importing.
                </p>
            @endif

            @if ($showPreview && count($previewSelectedIndices) > 0)
                <x-filament::button
                    color="success"
                    icon="heroicon-m-arrow-down-tray"
                    wire:click="$dispatch('open-modal', { id: 'confirm-import-transactions' })"
                >
                    Confirm &amp; Import ({{ count($previewSelectedIndices) }})
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

        {{-- Confirm & Import modal (same pattern as backup-delete / purge-database) --}}
        @if ($showPreview && count($previewSelectedIndices) > 0)
            <x-filament::modal id="confirm-import-transactions" width="md">
                <x-slot name="heading">Import selected transactions</x-slot>
                <x-slot name="description">
                    Import {{ count($previewSelectedIndices) }} selected transaction(s) into the external bank account? You can then post them to Master Bank from Import Sessions.
                </x-slot>
                <x-slot name="footerActions">
                    <x-filament::button
                        color="success"
                        icon="heroicon-m-arrow-down-tray"
                        wire:click="confirmAndImportSubmit"
                    >
                        Import
                    </x-filament::button>
                    <x-filament::button
                        color="gray"
                        x-on:click="$dispatch('close-modal', { id: 'confirm-import-transactions' })"
                    >
                        Cancel
                    </x-filament::button>
                </x-slot>
            </x-filament::modal>
        @endif
    </x-filament::section>

    {{-- ── Step 3: Review import history ───────────────────────────────── --}}
    <x-filament::section
        class="mt-6 rounded-2xl border border-slate-100/80 bg-white/80 dark:bg-slate-950/80 backdrop-blur-sm shadow-sm"
    >
        <x-slot name="heading">
            <span class="flex items-center gap-2">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-indigo-500 text-xs font-semibold text-white">2</span>
                <span>Import History</span>
            </span>
        </x-slot>
        <x-slot name="description">
            <span class="block text-xs text-slate-700 dark:text-slate-300">
                Recently imported external transactions. Use filters and bulk actions to work with multiple rows at once.
            </span>
        </x-slot>

        {{ $this->table }}
    </x-filament::section>
    </div>

</x-filament-panels::page>
