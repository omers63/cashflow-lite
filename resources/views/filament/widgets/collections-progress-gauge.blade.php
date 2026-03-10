<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Collections Progress</x-slot>
        <x-slot name="description">{{ $this->getData()['period_label'] }} — expected vs realized</x-slot>

        @php $data = $this->getData(); @endphp

        <div class="space-y-4">
            {{-- Progress bar --}}
            <div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="font-medium text-gray-700 dark:text-gray-300">
                        {{ $data['progress_percent'] }}% collected
                    </span>
                    <span class="text-gray-500 dark:text-gray-400">
                        ${{ number_format($data['realized'], 2) }} / ${{ number_format($data['expected'], 2) }}
                    </span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                    <div
                        class="h-3 rounded-full transition-all duration-500 {{ $data['progress_percent'] >= 90 ? 'bg-success-500' : ($data['progress_percent'] >= 50 ? 'bg-warning-500' : 'bg-danger-500') }}"
                        style="width: {{ $data['progress_percent'] }}%"
                    ></div>
                </div>
            </div>

            {{-- Stats row --}}
            <div class="grid grid-cols-3 gap-4">
                <div class="text-center p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Shortfall</p>
                    <p class="text-lg font-bold {{ $data['shortfall'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}">
                        ${{ number_format($data['shortfall'], 2) }}
                    </p>
                </div>
                <div class="text-center p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Members with shortfall</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">
                        {{ $data['members_with_shortfall'] }}
                    </p>
                </div>
                <div class="text-center p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Total members</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">
                        {{ $data['total_members'] }}
                    </p>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
