<div class="ee-section">
    <x-eagle-eye-filament::card heading="Busiest actors" subheading="By events in the selected range">
        @if($this->backendUnavailable())
            <x-eagle-eye-filament::empty message="Backend unavailable, try a narrower time range" />
        @else
            @php($rows = $this->getRows())

            @if([] === $rows)
                <x-eagle-eye-filament::empty message="No actors recorded in the selected range." />
            @else
                <div class="ee-actors" role="list" aria-label="Busiest actors">
                    @foreach($rows as $row)
                        @if(null !== $row['url'])
                            <a class="ee-actor" role="listitem" href="{{ $row['url'] }}">
                        @else
                            <div class="ee-actor" role="listitem">
                        @endif
                            <span class="ee-actor__name">{{ $row['actor'] }}</span>
                            <span class="ee-actor__meta">{{ $row['role'] }}</span>
                            <span class="ee-actor__meta ee-mono">{{ $row['events'] }} events</span>
                            @if(null !== $row['errors'])
                                <span class="ee-chip ee-chip--{{ $row['errors'] > 0 ? 'red' : 'neutral' }}">{{ $row['errors'] }} errors</span>
                            @endif
                        @if(null !== $row['url'])
                            </a>
                        @else
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        @endif
    </x-eagle-eye-filament::card>
</div>
