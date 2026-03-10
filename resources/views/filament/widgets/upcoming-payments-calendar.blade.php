<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Upcoming Payments (Next 14 Days)</x-slot>
        <x-slot name="description">
            Total expected: ${{ number_format($this->getTotalExpected(), 2) }}
        </x-slot>

        @php $grouped = $this->getUpcomingPayments(); @endphp

        @if (empty($grouped))
            <div class="flex items-center gap-3 py-4 text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-calendar" class="w-6 h-6" />
                <span class="text-sm font-medium">No payments due in the next 14 days.</span>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($grouped as $dateKey => $group)
                    <div class="rounded-lg border {{ $group['is_today'] ? 'border-primary-300 dark:border-primary-600 bg-primary-50 dark:bg-primary-900/10' : ($group['is_past'] ? 'border-danger-300 dark:border-danger-600' : 'border-gray-200 dark:border-gray-700') }}">
                        <div class="px-4 py-2 {{ $group['is_today'] ? 'bg-primary-100 dark:bg-primary-900/20' : ($group['is_past'] ? 'bg-danger-50 dark:bg-danger-900/20' : 'bg-gray-50 dark:bg-gray-800') }} rounded-t-lg">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                    {{ $group['label'] }}
                                    @if ($group['is_today'])
                                        <x-filament::badge color="primary" class="ml-2">Today</x-filament::badge>
                                    @endif
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ count($group['items']) }} payment(s)
                                </span>
                            </div>
                        </div>
                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($group['items'] as $item)
                                <div class="px-4 py-2 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <x-filament::icon icon="heroicon-o-user" class="w-4 h-4 text-gray-400" />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['member'] }}</span>
                                        <span class="text-xs text-gray-400">{{ $item['loan_id'] }}</span>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        ${{ number_format($item['amount'], 2) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
