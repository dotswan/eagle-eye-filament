<x-filament-panels::page>
    @php($filterError = $this->getFilterError())

    <div class="ee ee-page">
        <x-eagle-eye-filament::page-shell
            active="explorer"
            eyebrow="Activity Log"
            title="Activity explorer"
            :subtitle="$this->getResultLabel()"
        >
            <x-slot:actions>
                <x-eagle-eye-filament::segmented :items="$this->getRangeOptions()" />
                <x-eagle-eye-filament::segmented :items="$this->getVariantItems()" />
            </x-slot:actions>

            @if($this->customRangeOpen)
                <div class="ee-range">
                    <label class="ee-range__field">
                        <span class="ee-range__label">From</span>
                        <input type="date" class="ee-range__input" wire:model="draftFrom">
                    </label>
                    <label class="ee-range__field">
                        <span class="ee-range__label">Until</span>
                        <input type="date" class="ee-range__input" wire:model="draftUntil">
                    </label>
                    <button type="button" class="ee-btn" wire:click="applyCustomRange">Apply</button>
                    <button type="button" class="ee-link" wire:click="cancelCustomRange">Cancel</button>
                </div>
            @endif

            @if(null !== $filterError)
                <div class="ee-inline-error" role="alert">{{ $filterError }}</div>
            @endif

            <div
                class="ee-explorer"
                x-data="{ wide: false, narrow: false }"
                x-init="const observer = new ResizeObserver(([entry]) => { const width = entry.contentRect.width; wide = width >= 1100; narrow = width < 760; }); observer.observe($el);"
            >
                <div class="ee-explorer__facets" role="complementary" aria-label="Filters">
                    <details class="ee-filters" x-data="{ open: false }" x-effect="open = wide" :open="open" @toggle="open = $event.target.open">
                        <summary class="ee-filters__summary">Filters · {{ count($this->getFilterChips()) }} active</summary>
                        <div class="ee-filters__body">
                            @include('eagle-eye-filament::partials.explorer-facets')
                        </div>
                    </details>
                </div>

                <section class="ee-explorer__list" aria-label="Activity results">
                    @include('eagle-eye-filament::partials.explorer-search')
                    @include('eagle-eye-filament::partials.explorer-controls')

                    @if(null === $filterError)
                        @include('eagle-eye-filament::partials.explorer-pagination', ['activityPage' => $this->getActivityPage(), 'variant' => 'compact'])

                        @if('trace' === $this->getVariant())
                            @include('eagle-eye-filament::partials.explorer-trace')
                        @else
                            @include('eagle-eye-filament::partials.explorer-stream')
                        @endif
                    @endif
                </section>

                <div class="ee-explorer__pane" role="region" aria-label="Event detail">
                    <livewire:ee-explorer-pane :initial-id="$this->getFirstEntryId()" :key="'pane-'.$this->activityCursorSignature.'-'.$this->getVariant()" />
                </div>
            </div>
        </x-eagle-eye-filament::page-shell>
    </div>
</x-filament-panels::page>
