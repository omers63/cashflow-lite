<x-filament-panels::page>
    <div class="mb-6 flex flex-wrap items-center gap-2 text-sm">
        @foreach ([1 => 'Import', 2 => 'Member', 3 => 'Fund', 4 => 'Review'] as $n => $label)
            <span @class([
                'inline-flex items-center gap-1 rounded-full px-3 py-1 font-medium',
                'bg-primary-600 text-white' => (int) $this->step === $n,
                'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' => (int) $this->step !== $n,
            ])>
                <span class="tabular-nums">{{ $n }}</span>
                <span>{{ $label }}</span>
            </span>
            @if ($n < 4)
                <x-filament::icon icon="heroicon-o-chevron-right" class="h-4 w-4 text-gray-400 shrink-0" />
            @endif
        @endforeach
    </div>

    @if ($this->completed)
        <x-filament::section class="mb-6">
            <x-slot name="heading">Done</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                You can start another run below. The external bank account selection was kept for convenience.
            </p>
        </x-filament::section>
    @endif

    {{ $this->form }}

    @if ((int) $this->step === 4)
        <x-filament::section class="mt-6">
            <x-slot name="heading">Summary</x-slot>
            <div class="prose dark:prose-invert max-w-none text-sm">
                {!! $this->reviewSummaryHtml() !!}
            </div>
        </x-filament::section>
    @endif

    {{--
        Native buttons + Alpine $wire: Filament's <x-filament::button> merges attributes in a way that can
        drop or break Livewire's wire:click on the final DOM node, so clicks show a pointer but never fire.
        Calling $wire.method() from Alpine is the supported Livewire 3 pattern and bypasses that merge.
    --}}
    <div class="mt-6 flex flex-wrap gap-3">
        @if ((int) $this->step > 1)
            <button
                type="button"
                wire:key="wizard-back"
                class="fi-btn fi-size-md inline-grid grid-flow-col items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold outline-none transition duration-75 bg-gray-100 text-gray-800 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                x-on:click.prevent.stop="$wire.goToPreviousStep()"
            >
                <span wire:loading.remove wire:target="goToPreviousStep">Back</span>
                <span wire:loading wire:target="goToPreviousStep" class="opacity-70">Please wait…</span>
            </button>
        @endif

        @if ((int) $this->step < 4)
            <button
                type="button"
                wire:key="wizard-continue"
                class="fi-btn fi-size-md fi-btn-color-primary inline-grid grid-flow-col items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold outline-none transition duration-75"
                x-on:click.prevent.stop="$wire.goToNextStep()"
            >
                <span wire:loading.remove wire:target="goToNextStep">Continue</span>
                <span wire:loading wire:target="goToNextStep" class="opacity-70">Please wait…</span>
            </button>
        @else
            <button
                type="button"
                wire:key="wizard-run-pipeline"
                class="fi-btn fi-size-md fi-btn-color-success inline-grid grid-flow-col items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold outline-none transition duration-75"
                x-on:click.prevent.stop="$wire.runPipeline()"
            >
                <span wire:loading.remove wire:target="runPipeline">Run full pipeline</span>
                <span wire:loading wire:target="runPipeline" class="opacity-70">Working…</span>
            </button>
        @endif
    </div>
</x-filament-panels::page>
