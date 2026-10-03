@use('Dotswan\EagleEye\Data\ActivityEntry')
<div class="ee-pane">
    @php($entry = $this->getSelectedEntry())

    @if(! $entry instanceof ActivityEntry)
        <div class="ee-pane__empty">
            <x-eagle-eye-filament::empty title="No selection" message="Select an event to inspect it." />
        </div>
    @else
        @php($changeRows = $this->getChangeRows())
        @php($detailSections = $this->getDetailSections())

        <div class="ee-pane__head">
            <div class="ee-row">
                <x-eagle-eye-filament::chip :tone="$this->entryChannelTone($entry)">{{ $entry->channel->value }}</x-eagle-eye-filament::chip>
                <x-eagle-eye-filament::chip :tone="$this->entrySeverityTone($entry)" outline>{{ $entry->severity->value }}</x-eagle-eye-filament::chip>
                @if('' !== $entry->action)
                    <x-eagle-eye-filament::chip tone="action">{{ $entry->action }}</x-eagle-eye-filament::chip>
                @endif
                <span class="ee-spacer"></span>
                <a class="ee-link" href="{{ $this->getFullPageUrl() }}">Full page ↗</a>
            </div>
            <h3 class="ee-pane__title">{{ $entry->title }}</h3>
            <div class="ee-pane__meta">
                <span>{{ $entry->loggedAt->format('Y-m-d H:i:s') }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ $this->entryActorLabel($entry) }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ $this->entryRelativeTime($entry) }}</span>
            </div>
        </div>

        <div x-data="{ tab: 'details' }" x-on:ee-select.window="tab = 'details'" class="ee-pane__tabs">
            <div class="ee-tabs" role="tablist">
                <button type="button" role="tab" class="ee-tabs__item" @click="tab = 'details'" :class="{ 'ee-tabs__item--active': tab === 'details' }" :aria-selected="tab === 'details'">Details</button>
                @if([] !== $changeRows)
                    <button type="button" role="tab" class="ee-tabs__item" @click="tab = 'changes'" :class="{ 'ee-tabs__item--active': tab === 'changes' }" :aria-selected="tab === 'changes'">Changes</button>
                @endif
                <button type="button" role="tab" class="ee-tabs__item" @click="tab = 'payload'" :class="{ 'ee-tabs__item--active': tab === 'payload' }" :aria-selected="tab === 'payload'">Payload</button>
                <button type="button" role="tab" class="ee-tabs__item" @click="tab = 'context'; $wire.loadContext()" :class="{ 'ee-tabs__item--active': tab === 'context' }" :aria-selected="tab === 'context'">Context</button>
            </div>

            <div class="ee-pane__body" x-show="tab === 'details'">
                @forelse($detailSections as $section)
                    <div class="ee-pane__section">
                        <h4 class="ee-pane__section-title">{{ $section['title'] }}</h4>
                        <div class="ee-kv">
                            @foreach($section['rows'] as $row)
                                <x-eagle-eye-filament::kv-row :label="$row['label']" :value="$row['value']" :mono="$row['mono']" :type="$row['type'] ?? null" :href="$row['href'] ?? null" :truncated="$row['truncated'] ?? false" />
                            @endforeach
                        </div>
                    </div>
                @empty
                    <x-eagle-eye-filament::empty message="No structured details for this event." />
                @endforelse

                @php($origin = $this->getOrigin())
                @if([] !== $origin || null !== $this->getTraceUrl())
                    <div class="ee-pane__section">
                        <h4 class="ee-pane__section-title">Origin</h4>
                        <div class="ee-kv">
                            @foreach($origin as $row)
                                <x-eagle-eye-filament::kv-row :label="$row['label']" :value="$row['value']" :mono="$row['mono']" :type="$row['type'] ?? null" :href="$row['href'] ?? null" :truncated="$row['truncated'] ?? false" />
                            @endforeach
                            @if(null !== $this->getTraceUrl())
                                <x-eagle-eye-filament::kv-row label="Trace">
                                    <a class="ee-link" href="{{ $this->getTraceUrl() }}">{{ $entry->trace->traceId }}</a>
                                </x-eagle-eye-filament::kv-row>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div class="ee-pane__body" x-show="tab === 'changes'" x-cloak>
                <x-eagle-eye-filament::diff-table
                    variant="stacked"
                    :rows="$changeRows"
                    empty-message="No attribute changes recorded for this event."
                />
            </div>

            <div class="ee-pane__body" x-show="tab === 'payload'" x-cloak>
                <x-eagle-eye-filament::code-block
                    :code="$this->getPayloadPreview()"
                    :meta="$this->getPayloadSizeLabel()"
                    copy
                    max-height="320"
                />
            </div>

            <div class="ee-pane__body" x-show="tab === 'context'" x-cloak>
                @if($this->contextLoaded)
                    <div class="ee-pane__section">
                        <h4 class="ee-pane__section-title">Same trace</h4>
                        @forelse($this->getContextSiblings() as $sibling)
                            <button type="button" class="ee-pane__sibling" @click="$dispatch('ee-select', { id: @js($sibling->id) })">
                                <span class="ee-chip ee-chip--{{ $this->entryChannelTone($sibling) }}">{{ $sibling->channel->value }}</span>
                                <span class="ee-pane__sibling-title">{{ $sibling->title }}</span>
                            </button>
                        @empty
                            <x-eagle-eye-filament::empty message="No other events in this trace." />
                        @endforelse
                    </div>

                    @if(null !== $this->getSubjectCount())
                        <div class="ee-pane__section">
                            <h4 class="ee-pane__section-title">History of this subject</h4>
                            <p class="ee-pane__meta">
                                {{ $this->isSubjectCountExact() ? '' : '≈' }}{{ $this->getSubjectCount() }} events
                                @if(null !== $this->getSubjectHistoryUrl())
                                    · <a class="ee-link" href="{{ $this->getSubjectHistoryUrl() }}">Open record history →</a>
                                @endif
                            </p>
                        </div>
                    @endif
                @else
                    <p class="ee-pane__meta">Loading context…</p>
                @endif
            </div>
        </div>

        @if($this->getSealedCount() > 0)
            <div class="ee-pane__sealed">{{ $this->getSealedCount() }} sealed values: open full page to reveal</div>
        @endif
    @endif
</div>
