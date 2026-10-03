<x-filament-panels::page>
    @php($stats = $this->getStats())
    @php($spans = $this->getSpans())

    <div class="ee ee-page">
        <x-eagle-eye-filament::page-shell
            active="traces"
            eyebrow="Trace"
            :title="$this->getHeaderTitle()"
            :subtitle="$this->traceId"
        >

        @if($this->traceIsIncomplete())
            <div class="ee-detail__note" role="status">Showing the first 50 events of this trace.</div>
        @endif

        <div class="ee-trace" x-data="{ sel: @js($this->getSelectedId()) }">
            <header class="ee-card ee-trace__hero">
                <div class="ee-card__body ee-row">
                    <span class="ee-chip ee-chip--blue">trace</span>
                    <span class="ee-mono ee-trace__id">{{ $this->traceId }}</span>
                </div>
            </header>

            <x-eagle-eye-filament::stat-grid>
                <x-eagle-eye-filament::stat label="Duration" :value="$stats['duration']" :unit="$stats['durationIsSpan'] ? 'span' : null" />
                <x-eagle-eye-filament::stat label="Events" :value="($stats['eventsIncomplete'] ? '≥' : '').$stats['events']" />
                <x-eagle-eye-filament::stat label="Writes" :value="$stats['writes']" />
                <x-eagle-eye-filament::stat label="Errors" :value="$stats['errors']" />
            </x-eagle-eye-filament::stat-grid>

            @if([] === $spans)
                <x-eagle-eye-filament::empty title="No entries" message="No entries found for this trace." />
            @else
                <div class="ee-trace__columns">
                <div class="ee-stack">
                <x-eagle-eye-filament::card heading="Waterfall" flush>
                    <div class="ee-waterfall__axis" aria-hidden="true">
                        @foreach($this->getAxisTicks() as $tick)
                            <span>{{ $tick['label'] }}</span>
                        @endforeach
                    </div>

                    <div class="ee-waterfall" role="list" aria-label="Trace waterfall">
                        @foreach($spans as $span)
                            @php($barClasses = 'ee-waterfall__bar ee-waterfall__bar--'.$span['channelTone'].($span['hasDuration'] ? '' : ' ee-waterfall__bar--marker'))
                            <button
                                type="button"
                                role="listitem"
                                class="ee-waterfall__row"
                                x-bind:class="{ 'ee-waterfall__row--active': sel === @js($span['id']) }"
                                x-on:click="sel = @js($span['id'])"
                            >
                                <span class="ee-waterfall__label" style="padding-left: {{ 18 + ($span['depth'] * 14) }}px">
                                    <span class="ee-chip ee-chip--{{ $span['channelTone'] }}">{{ $span['channel'] }}</span>
                                    <span class="ee-waterfall__title">{{ $span['title'] }}</span>
                                </span>
                                <span class="ee-waterfall__track">
                                    <span
                                        class="{{ $barClasses }}"
                                        style="left: {{ $span['offsetPercent'] }}%@if($span['hasDuration']); width: {{ $span['widthPercent'] }}%@endif"
                                        title="{{ $span['offsetLabel'] }}"
                                    ></span>
                                    @if($span['hasDuration'])
                                        <span class="ee-waterfall__offset" style="left: {{ $span['durationLabelLeft'] }}%">{{ $span['durationLabel'] }}</span>
                                    @endif
                                </span>
                            </button>
                        @endforeach
                    </div>
                </x-eagle-eye-filament::card>

                <div class="ee-trace__legend">
                    <span class="ee-trace__legend-title">Channels</span>
                    @foreach($this->getLegend() as $item)
                        <x-eagle-eye-filament::chip :tone="$item['tone']">{{ $item['channel'] }}</x-eagle-eye-filament::chip>
                    @endforeach
                </div>
                </div>

                <aside class="ee-trace__panel" aria-live="polite">
                    @foreach($spans as $span)
                        <div class="ee-card ee-span" x-show="sel === @js($span['id'])" x-cloak>
                            <div class="ee-row">
                                <span class="ee-chip ee-chip--{{ $span['channelTone'] }}">{{ $span['channel'] }}</span>
                                @if('' !== $span['action'])
                                    <span class="ee-chip ee-chip--action">{{ $span['action'] }}</span>
                                @endif
                                <span class="ee-spacer"></span>
                                <a class="ee-link" href="{{ $span['eventUrl'] }}">Open full event →</a>
                            </div>
                            <h2 class="ee-span__title">{{ $span['title'] }}</h2>
                            <div class="ee-span__meta">
                                <span class="ee-mono">{{ $span['offsetLabel'] }}</span>
                                <span aria-hidden="true">·</span>
                                <span class="ee-mono">{{ $span['durationLabel'] ?? '—' }}</span>
                                <span aria-hidden="true">·</span>
                                <span>{{ $span['actor'] }}</span>
                            </div>
                            <x-eagle-eye-filament::code-block :code="$span['payloadPreview']" meta="payload preview" max-height="320" />
                        </div>
                    @endforeach
                </aside>
                </div>
            @endif
        </div>
        </x-eagle-eye-filament::page-shell>
    </div>
</x-filament-panels::page>
