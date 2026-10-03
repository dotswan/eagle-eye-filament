<div class="ee-section">
    @if($this->backendUnavailable())
        <x-eagle-eye-filament::card heading="Failures" subheading="Errors in the last hour">
            <x-eagle-eye-filament::empty message="Backend unavailable, try a narrower time range" />
        </x-eagle-eye-filament::card>
    @else
        @php($alert = $this->getAlert())

        @if(null !== $alert)
            <x-eagle-eye-filament::card heading="Failures" subheading="Errors in the last hour">
                <div class="ee-alert">
                    <span class="ee-status-dot ee-status-dot--red" aria-hidden="true"></span>
                    <strong>{{ $alert['approximate'] ? '≥ ' : '' }}{{ $alert['count'] }} traces failed in the last hour</strong>
                    @if(null !== $alert['title'])
                        <span class="ee-detail__muted">{{ $alert['title'] }}</span>
                    @endif
                    <span class="ee-spacer"></span>
                    @if(null !== $alert['url'])
                        <a class="ee-link" href="{{ $alert['url'] }}">Open latest →</a>
                    @endif
                    <a class="ee-link" href="{{ $this->getExplorerUrl() }}">Open in explorer →</a>
                </div>
            </x-eagle-eye-filament::card>
        @endif
    @endif
</div>
