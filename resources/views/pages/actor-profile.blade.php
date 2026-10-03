@use('Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource')
@php($entries = $this->getRecentEntries())

<x-filament-panels::page>
    <div class="ee ee-page">
        <x-eagle-eye-filament::page-shell
            active="explorer"
            eyebrow="Actor"
            :title="$this->getActorLabel()"
            :subtitle="$this->getRoleLabel().' · '.$this->actorType.':'.$this->actorId"
        >
            <x-slot:actions>
                <a class="ee-link" href="{{ $this->getExplorerUrl() }}">Open in explorer →</a>
            </x-slot:actions>

        <header class="ee-card">
            <div class="ee-card__body ee-row">
                <span class="ee-detail__avatar" aria-hidden="true">{{ $this->getActorInitials() }}</span>
                <div class="ee-stack ee-profile__identity">
                    <strong class="ee-profile__name">{{ $this->getActorLabel() }}</strong>
                    <span class="ee-detail__muted">{{ $this->getRoleLabel() }}</span>
                </div>
                <span class="ee-spacer"></span>
                @if($this->insightsLoaded)
                    @php($lastSeen = $this->getLastSeen())
                    @if(null !== $lastSeen)
                        <span class="ee-detail__muted">Last seen {{ $lastSeen }}</span>
                    @endif
                @endif
            </div>
        </header>

        @if(! $this->insightsLoaded)
            <x-eagle-eye-filament::card heading="Statistics" subheading="Last 7 days">
                <div class="ee-facets__loading" wire:init="loadInsights">Loading statistics…</div>
            </x-eagle-eye-filament::card>
        @else
            @php($stats = $this->getStats())
            @php($heatmap = $this->getHeatmap())
            @php($topActions = $this->getTopActions())

            @if($this->seriesUnavailable())
                <x-eagle-eye-filament::card heading="Statistics" subheading="Last 7 days">
                    <x-eagle-eye-filament::empty message="Activity statistics are not supported by the active log backend." />
                </x-eagle-eye-filament::card>
            @else
                <x-eagle-eye-filament::card heading="Statistics" subheading="Last 7 days">
                    <x-eagle-eye-filament::stat-grid>
                        <x-eagle-eye-filament::stat label="Events" :value="$stats['events']" unit="7d" />
                        <x-eagle-eye-filament::stat label="Writes" :value="$stats['writes']" unit="7d" />
                        <x-eagle-eye-filament::stat
                            label="Errors"
                            :value="$stats['errorsAvailable'] ? (($stats['exact'] ? '' : '≈').$stats['errors']) : '—'"
                            unit="7d"
                        />
                    </x-eagle-eye-filament::stat-grid>
                </x-eagle-eye-filament::card>

                <x-eagle-eye-filament::card heading="Events per hour" subheading="Last 7 calendar days">
                    @if([] === $heatmap['rows'])
                        <x-eagle-eye-filament::empty message="No activity in the last 7 days." />
                    @else
                        <div class="ee-heat">
                            <div class="ee-heat__days" aria-hidden="true">
                                @foreach($heatmap['rows'] as $row)
                                    <span class="ee-heat__day">{{ $row['label'] }}</span>
                                @endforeach
                            </div>
                            <div class="ee-heat__grid">
                                <div class="ee-heat__hours" aria-hidden="true">
                                    @for($hour = 0; $hour < 24; $hour++)
                                        <span>{{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}</span>
                                    @endfor
                                </div>
                                <div class="ee-heat__rows">
                                    @foreach($heatmap['rows'] as $row)
                                        <div class="ee-heat__row">
                                            @foreach($row['cells'] as $cell)
                                                <span
                                                    @class(['ee-heat__cell', 'ee-heat__cell--'.$cell['level'] => $cell['level'] > 0])
                                                    title="{{ $cell['label'] }}"
                                                    aria-label="{{ $cell['label'] }}"
                                                ></span>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="ee-heat__legend" aria-hidden="true">
                            <span class="ee-detail__muted">Less</span>
                            @for($level = 0; $level <= 4; $level++)
                                <span @class(['ee-heat__cell ee-heat__legend-cell', 'ee-heat__cell--'.$level => $level > 0])></span>
                            @endfor
                            <span class="ee-detail__muted">More</span>
                        </div>
                    @endif
                </x-eagle-eye-filament::card>
            @endif

            <x-eagle-eye-filament::card heading="Most used actions" subheading="Last 7 days">
                @if($this->topActionsUnavailable())
                    <x-eagle-eye-filament::empty message="Action ranking is not supported by the active log backend." />
                @elseif([] === $topActions)
                    <x-eagle-eye-filament::empty message="No actions recorded in the last 7 days." />
                @else
                    <div class="ee-actions">
                        @foreach($topActions as $row)
                            <div class="ee-actions__row">
                                <span class="ee-actions__label ee-mono">{{ $row['action'] }}</span>
                                <span class="ee-actions__track" role="img" aria-label="{{ $row['action'] }}: {{ $row['count'] }} events">
                                    <span class="ee-actions__fill" style="width: {{ max(0.0, min(100.0, (float) $row['percent'])) }}%"></span>
                                </span>
                                <span class="ee-actions__count ee-mono">{{ $row['count'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-eagle-eye-filament::card>
        @endif

        <x-eagle-eye-filament::card heading="Recent activity" flush>
            @if([] === $entries)
                <x-eagle-eye-filament::empty title="No activity" message="This actor has no recorded events." />
            @else
                <div class="ee-list" role="list" aria-label="Recent activity">
                    @foreach($entries as $entry)
                        <x-eagle-eye-filament::entry-row
                            :entry="$entry"
                            :href="ActivityLogResource::getUrl('view', ['record' => $entry->id])"
                        />
                    @endforeach
                </div>

                @include('eagle-eye-filament::partials.explorer-pagination', ['activityPage' => $this->getActivityPage(), 'variant' => 'full'])
            @endif
        </x-eagle-eye-filament::card>
        </x-eagle-eye-filament::page-shell>
    </div>
</x-filament-panels::page>
