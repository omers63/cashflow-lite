<x-filament-panels::page>

    <x-filament::section>
        <x-slot name="heading">Soft-Deleted Records</x-slot>
        <x-slot name="description">
            These records have been soft-deleted and are still stored in the database.
            Purging permanently removes them and cannot be undone.
        </x-slot>

        @php $counts = $this->getTrashedCounts(); $total = array_sum($counts); @endphp

        @if ($total === 0)
            <div class="flex items-center gap-3 text-success-600 dark:text-success-400 py-4">
                <x-filament::icon icon="heroicon-o-check-circle" class="w-6 h-6" />
                <span class="text-sm font-medium">No soft-deleted records found. The database is clean.</span>
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Table</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Deleted Records</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                        @foreach ($counts as $label => $count)
                            <tr wire:key="row-{{ $label }}">
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                    {{ $label }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($count > 0)
                                        <x-filament::badge color="danger">{{ number_format($count) }}</x-filament::badge>
                                    @else
                                        <x-filament::badge color="success">0</x-filament::badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($count > 0)
                                        <x-filament::button
                                            color="danger"
                                            size="sm"
                                            icon="heroicon-m-trash"
                                            wire:click="$dispatch('open-modal', { id: 'confirm-purge-{{ Str::slug($label) }}' })"
                                        >
                                            Purge
                                        </x-filament::button>

                                        <x-filament::modal id="confirm-purge-{{ Str::slug($label) }}" width="md">
                                            <x-slot name="heading">Purge {{ $label }}?</x-slot>
                                            <x-slot name="description">
                                                Permanently delete {{ number_format($count) }} soft-deleted {{ strtolower($label) }} record(s)?
                                                This cannot be undone.
                                            </x-slot>
                                            <x-slot name="footerActions">
                                                <x-filament::button
                                                    color="danger"
                                                    wire:click="purgeModel('{{ $label }}')"
                                                    x-on:click="$dispatch('close-modal', { id: 'confirm-purge-{{ Str::slug($label) }}' })"
                                                >
                                                    Yes, purge {{ $label }}
                                                </x-filament::button>
                                                <x-filament::button
                                                    color="gray"
                                                    x-on:click="$dispatch('close-modal', { id: 'confirm-purge-{{ Str::slug($label) }}' })"
                                                >
                                                    Cancel
                                                </x-filament::button>
                                            </x-slot>
                                        </x-filament::modal>
                                    @else
                                        <span class="text-gray-400 text-xs">–</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <td class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-200">Total</td>
                            <td class="px-4 py-3 text-right">
                                <x-filament::badge color="danger">{{ number_format($total) }}</x-filament::badge>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>

</x-filament-panels::page>
