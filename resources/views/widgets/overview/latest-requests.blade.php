<div class="ee-section">
    <x-eagle-eye-filament::card heading="Latest requests">
        <x-slot:actions>
            <a class="ee-link" href="{{ $this->getTracesUrl() }}">Open traces →</a>
        </x-slot:actions>

        @if($this->backendUnavailable())
            <x-eagle-eye-filament::empty message="Backend unavailable, try a narrower time range" />
        @else
            @php($rows = $this->getRows())

            @if([] === $rows)
                <x-eagle-eye-filament::empty message="No HTTP requests in the selected range." />
            @else
                <div class="ee-latest" role="list" aria-label="Latest HTTP requests">
                    @foreach($rows as $row)
                        <a class="ee-latest__row" role="listitem" href="{{ $row['url'] }}">
                            <span class="ee-status-dot ee-status-dot--{{ $row['statusTone'] }}" aria-hidden="true"></span>
                            <span class="ee-latest__title">{{ $row['title'] }}</span>
                            <span class="ee-latest__meta ee-mono">{{ $row['method'] ?? '—' }} {{ $row['statusCode'] ?? '' }}</span>
                            <span class="ee-latest__meta ee-mono">{{ $row['durationLabel'] }}</span>
                            <span class="ee-latest__meta">{{ $row['actor'] }}</span>
                            <span class="ee-latest__meta ee-mono" title="{{ $row['time'] }}">{{ $row['relativeTime'] }}</span>
                            <span class="ee-latest__meta ee-mono">{{ null === $row['events'] ? '—' : $row['events'].' events' }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        @endif
    </x-eagle-eye-filament::card>
</div>
