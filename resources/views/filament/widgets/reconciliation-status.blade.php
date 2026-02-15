<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Latest Reconciliation
        </x-slot>

        @if($this->latest)
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span class="font-medium">Date:</span>
                    <span>{{ $this->latest->reconciliation_date->format('M d, Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-medium">Status:</span>
                    <span class="@if($this->latest->all_passed) text-success-600 @else text-danger-600 @endif">
                        {{ $this->latest->all_passed ? 'PASSED' : 'FAILED' }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="font-medium">Checks Passed:</span>
                    <span>{{ $this->latest->checks_passed }}/7</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-medium">Pass Rate (Month):</span>
                    <span>{{ $this->month_pass_rate }}%</span>
                </div>
            </div>
        @else
            <p class="text-gray-500">No reconciliations yet</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
