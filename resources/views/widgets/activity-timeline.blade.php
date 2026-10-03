<x-filament-widgets::widget>
    <x-filament::section heading="Activity">
        <div class="ee">
            @php($entries = $this->getEntries())
            @if([] === $entries)
                <p class="ee-widget__empty">No activity recorded yet.</p>
            @else
                <ul class="ee-widget">
                    @foreach($entries as $entry)
                        <li class="ee-widget__row">
                            <a class="ee-widget__title" href="{{ $this->getEntryUrl($entry) }}">{{ $entry->title }}</a>
                            <span class="ee-widget__time ee-mono">{{ $entry->loggedAt->format('Y-m-d H:i:s') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            @php($historyUrl = $this->getSubjectHistoryUrl())
            @if(null !== $historyUrl)
                <div class="ee-widget__footer">
                    <a class="ee-link" href="{{ $historyUrl }}">View full history →</a>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
