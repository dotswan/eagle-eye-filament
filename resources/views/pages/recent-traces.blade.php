<x-filament-panels::page>
    @php($rows = $this->getRows())

    <div class="ee ee-page">
        <x-eagle-eye-filament::page-shell
            active="traces"
            eyebrow="Activity Log"
            title="Traces"
            subtitle="Latest HTTP requests as trace roots · 20 per page"
        >

        <x-eagle-eye-filament::card heading="Requests" subheading="HTTP requests are trace roots. Event counts cover the trace window." flush>
            <div class="ee-traces__head">
                <span class="ee-list__count">Requests</span>
                <span class="ee-traces__filter-label">Channel</span>
                <x-eagle-eye-filament::chip tone="blue">http</x-eagle-eye-filament::chip>
            </div>

            @if([] === $rows)
                <x-eagle-eye-filament::empty title="No traces" message="No HTTP requests were recorded." />
            @else
                <div class="ee-traces" role="list" aria-label="Recent request traces">
                    <div class="ee-trace-row ee-trace-row--head" aria-hidden="true">
                        <span></span>
                        <span>Request</span>
                        <span>Status</span>
                        <span>Duration</span>
                        <span>Actor</span>
                        <span>When</span>
                        <span>Events (±10 min)</span>
                    </div>
                    @foreach($rows as $row)
                        <a class="ee-trace-row" role="listitem" href="{{ $row['url'] }}">
                            <span class="ee-status-dot ee-status-dot--{{ $row['statusTone'] }}" aria-hidden="true"></span>
                            <span class="ee-trace-row__main">
                                <span class="ee-trace-row__title">{{ $row['title'] }}</span>
                                <span class="ee-trace-row__route ee-mono">
                                    {{ $row['method'] ?? '—' }}@if(null !== $row['route']) {{ $row['route'] }}@endif
                                </span>
                            </span>
                            <span class="ee-trace-row__status ee-mono">{{ $row['statusCode'] ?? '—' }}</span>
                            <span class="ee-trace-row__duration ee-mono ee-trace-row__duration--{{ $row['durationTone'] }}">{{ $row['durationLabel'] }}</span>
                            <span class="ee-trace-row__actor">{{ $row['actor'] }}</span>
                            <span class="ee-trace-row__time ee-mono" title="{{ $row['time'] }}">{{ $row['relativeTime'] }}</span>
                            <span class="ee-trace-row__events ee-mono">{{ null === $row['events'] ? '—' : $row['events'] }}</span>
                        </a>
                    @endforeach
                </div>

                @include('eagle-eye-filament::partials.explorer-pagination', ['activityPage' => $this->getActivityPage(), 'variant' => 'full'])
            @endif
        </x-eagle-eye-filament::card>
        </x-eagle-eye-filament::page-shell>
    </div>
</x-filament-panels::page>
