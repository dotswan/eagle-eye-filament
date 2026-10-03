@php($entries = $this->getEntries())

@if([] === $entries)
    <x-eagle-eye-filament::empty title="No events" message="No activity matches these filters." />
@else
    <div
        class="ee-list"
        role="listbox"
        aria-label="Activity entries"
        tabindex="0"
        x-data="{ active: 0, selected: null }"
        @keydown.arrow-down.prevent="active = Math.min(active + 1, {{ count($entries) - 1 }}); $el.querySelectorAll('[role=option]')[active]?.scrollIntoView({ block: 'nearest' })"
        @keydown.arrow-up.prevent="active = Math.max(active - 1, 0); $el.querySelectorAll('[role=option]')[active]?.scrollIntoView({ block: 'nearest' })"
        @keydown.enter.prevent="$el.querySelectorAll('[role=option]')[active]?.click()"
    >
        @foreach($entries as $entry)
            <x-eagle-eye-filament::entry-row
                :entry="$entry"
                :select-id="$entry->id"
            />
        @endforeach
    </div>

    @include('eagle-eye-filament::partials.explorer-pagination', ['activityPage' => $this->getActivityPage(), 'variant' => 'full'])
@endif
