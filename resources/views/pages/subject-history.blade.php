@use('Dotswan\EagleEyeFilament\Support\Presentation\ActivityNav')
@php($versions = $this->getVersions())
@php($subjectLabel = $this->subjectType.' '.$this->subjectId)

<x-filament-panels::page>
    <div class="ee ee-page">
        <x-eagle-eye-filament::page-shell
            active="explorer"
            eyebrow="Record history"
            :title="$subjectLabel"
            :subtitle="$this->subjectId"
        >
            <x-slot:actions>
                <a class="ee-link" href="{{ ActivityNav::explorerUrl(['subjectType' => $this->subjectType, 'subjectId' => $this->subjectId], '30d') }}">Open in explorer →</a>
            </x-slot:actions>

        <header class="ee-card">
            <div class="ee-card__body ee-stack">
                <div class="ee-detail__muted ee-mono">{{ $subjectLabel }}</div>
            </div>
        </header>

        @if(! $this->statsLoaded)
            <x-eagle-eye-filament::card heading="Statistics">
                <div class="ee-facets__loading" wire:init="loadStats">Loading statistics…</div>
            </x-eagle-eye-filament::card>
        @else
            @php($stats = $this->getStats())
            <x-eagle-eye-filament::card heading="Statistics">
                <x-eagle-eye-filament::stat-grid>
                    <x-eagle-eye-filament::stat
                        label="Events"
                        :value="null === $stats['events'] ? '—' : (($stats['eventsApprox'] ? '≈' : '').$stats['events'])"
                    />
                    <x-eagle-eye-filament::stat label="Editors" :value="$stats['editors']" />
                    <x-eagle-eye-filament::stat
                        label="First seen"
                        :value="null === $stats['firstSeen'] ? '—' : (($stats['firstSeenApprox'] ? '≥ ' : '').$stats['firstSeen'])"
                    />
                </x-eagle-eye-filament::stat-grid>
            </x-eagle-eye-filament::card>
        @endif

        <x-eagle-eye-filament::card heading="Version history" flush>
            @if([] === $versions)
                <x-eagle-eye-filament::empty title="No history" message="This record has no recorded events." />
            @else
                <div class="ee-versions" role="list" aria-label="Record versions">
                    @foreach($versions as $version)
                        <article class="ee-version" role="listitem">
                            <header class="ee-version__head">
                                <span class="ee-version__time ee-mono">
                                    {{ $version['time'] }}
                                    @if(null !== $version['date'])
                                        <span class="ee-detail__muted">{{ $version['date'] }}</span>
                                    @endif
                                </span>
                                <span class="ee-detail__muted">{{ $version['relative'] }}</span>
                                <span class="ee-detail__muted">{{ $version['actor'] }}</span>
                                <x-eagle-eye-filament::chip :tone="$version['tone']">{{ $version['action'] }}</x-eagle-eye-filament::chip>
                                <span class="ee-spacer"></span>
                                <a class="ee-link" href="{{ $version['url'] }}">event ↗</a>
                            </header>
                            <p class="ee-version__title">{{ $version['title'] }}</p>
                            @if([] !== $version['changes'])
                                <x-eagle-eye-filament::diff-table variant="table" :rows="$version['changes']" />
                            @endif
                        </article>
                    @endforeach
                </div>

                @include('eagle-eye-filament::partials.explorer-pagination', ['activityPage' => $this->getActivityPage(), 'variant' => 'full'])
            @endif
        </x-eagle-eye-filament::card>
        </x-eagle-eye-filament::page-shell>
    </div>
</x-filament-panels::page>
