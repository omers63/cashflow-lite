<x-filament-panels::page>

    {{-- Current Database Info --}}
    <x-filament::section>
        <x-slot name="heading">Current Database</x-slot>
        <x-slot name="description">
            Information about the active SQLite database file.
        </x-slot>

        @php $dbInfo = $this->getDatabaseInfo(); @endphp

        @if ($dbInfo['exists'])
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-500/10">
                        <x-filament::icon icon="heroicon-o-circle-stack" class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">File Size</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ \App\Filament\Pages\BackupDatabase::formatBytes($dbInfo['size']) }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-500/10">
                        <x-filament::icon icon="heroicon-o-clock" class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Last Modified</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ $dbInfo['modified'] }}
                        </p>
                    </div>
                </div>
            </div>
        @else
            <div class="flex items-center gap-3 text-danger-600 dark:text-danger-400 py-4">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-6 h-6" />
                <span class="text-sm font-medium">Database file not found.</span>
            </div>
        @endif
    </x-filament::section>

    {{-- Existing Backups --}}
    <x-filament::section>
        <x-slot name="heading">Existing Backups</x-slot>
        <x-slot name="description">
            Backup files stored in <code class="text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">storage/app/backups/</code>.
            You can download or delete individual backups.
        </x-slot>

        @php $backups = $this->getBackups(); @endphp

        @if (count($backups) === 0)
            <div class="flex items-center gap-3 text-gray-500 dark:text-gray-400 py-4">
                <x-filament::icon icon="heroicon-o-inbox" class="w-6 h-6" />
                <span class="text-sm font-medium">No backups found. Click "Create Backup" to create one.</span>
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Filename</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Size</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Created</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                        @foreach ($backups as $backup)
                            <tr wire:key="backup-{{ $backup['name'] }}">
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                    <div class="flex items-center gap-2">
                                        <x-filament::icon icon="heroicon-o-document" class="w-4 h-4 text-gray-400" />
                                        {{ $backup['name'] }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <x-filament::badge color="info">
                                        {{ \App\Filament\Pages\BackupDatabase::formatBytes($backup['size']) }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">
                                    {{ $backup['date'] }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <x-filament::button
                                            color="gray"
                                            size="sm"
                                            icon="heroicon-m-arrow-down-tray"
                                            wire:click="downloadBackup('{{ $backup['name'] }}')"
                                        >
                                            Download
                                        </x-filament::button>

                                        <x-filament::button
                                            color="danger"
                                            size="sm"
                                            icon="heroicon-m-trash"
                                            wire:click="$dispatch('open-modal', { id: 'confirm-delete-{{ Str::slug($backup['name']) }}' })"
                                        >
                                            Delete
                                        </x-filament::button>

                                        <x-filament::modal id="confirm-delete-{{ Str::slug($backup['name']) }}" width="md">
                                            <x-slot name="heading">Delete {{ $backup['name'] }}?</x-slot>
                                            <x-slot name="description">
                                                This backup will be permanently deleted. This action cannot be undone.
                                            </x-slot>
                                            <x-slot name="footerActions">
                                                <x-filament::button
                                                    color="danger"
                                                    wire:click="deleteBackup('{{ $backup['name'] }}')"
                                                    x-on:click="$dispatch('close-modal', { id: 'confirm-delete-{{ Str::slug($backup['name']) }}' })"
                                                >
                                                    Yes, delete
                                                </x-filament::button>
                                                <x-filament::button
                                                    color="gray"
                                                    x-on:click="$dispatch('close-modal', { id: 'confirm-delete-{{ Str::slug($backup['name']) }}' })"
                                                >
                                                    Cancel
                                                </x-filament::button>
                                            </x-slot>
                                        </x-filament::modal>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <td class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-200">
                                {{ count($backups) }} backup(s)
                            </td>
                            <td class="px-4 py-3 text-right">
                                <x-filament::badge color="info">
                                    {{ \App\Filament\Pages\BackupDatabase::formatBytes(array_sum(array_column($backups, 'size'))) }}
                                </x-filament::badge>
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>

</x-filament-panels::page>
