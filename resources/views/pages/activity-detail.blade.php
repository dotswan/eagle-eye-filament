@use('Dotswan\EagleEyeFilament\Support\Presentation\EntryFormatter')
@use('Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource')
<x-filament-panels::page>
    @php($entry = $this->getEntry())
    @php($formatter = app(EntryFormatter::class))
    @php($neighbors = $this->getNeighbors())
    @php($windowExceeded = $this->traceWindowExceeded())
    @php($sealedPaths = $this->getSealedPaths())
    @php($actorUrl = $this->getActorUrl())
    @php($subjectUrl = $this->getSubjectUrl())
    @php($traceUrl = $this->getTraceUrl())
    @php($changeRows = $this->getChangeRows())

    <div class="ee ee-page">
        <x-eagle-eye-filament::page-shell
            active="explorer"
            eyebrow="Event"
            :title="$entry->title"
            :subtitle="$entry->channel->value.' · '.$entry->action.' · '.$entry->loggedAt->format('Y-m-d H:i').' UTC'"
            :breadcrumbs="$this->getBreadcrumbItems()"
        >
            <x-slot:actions>
                @unless($windowExceeded)
                    <nav class="ee-row ee-detail__nav" aria-label="Adjacent events">
                        @if(null !== $neighbors['prev'])
                            <a class="ee-btn" href="{{ $neighbors['prev'] }}" rel="prev">← Previous</a>
                        @else
                            <span class="ee-btn" aria-disabled="true">← Previous</span>
                        @endif
                        @if(null !== $neighbors['next'])
                            <a class="ee-btn" href="{{ $neighbors['next'] }}" rel="next">Next →</a>
                        @else
                            <span class="ee-btn" aria-disabled="true">Next →</span>
                        @endif
                    </nav>
                @endunless
            </x-slot:actions>

        <header class="ee-card ee-detail__hero">
            <div class="ee-card__body ee-stack">
                <div class="ee-row">
                    <span class="ee-chip ee-chip--{{ $formatter->channelTone($entry->channel) }}">{{ $entry->channel->value }}</span>
                    <span class="ee-chip ee-chip--{{ $formatter->severityTone($entry->severity) }} ee-chip--outline">{{ $entry->severity->value }}</span>
                    <span class="ee-chip ee-chip--action">{{ $entry->action }}</span>
                </div>

                @if(null !== $entry->summary)
                    <p class="ee-detail__summary">{{ $entry->summary }}</p>
                @endif

                <x-eagle-eye-filament::stat-grid class="ee-detail__meta" variant="meta">
                    <x-eagle-eye-filament::stat size="meta" label="When" :value="$entry->loggedAt->format('Y-m-d H:i:s')" unit="UTC" mono />
                    <x-eagle-eye-filament::stat size="meta" label="Channel" :value="$entry->channel->value" />
                    <x-eagle-eye-filament::stat size="meta" label="Severity" :value="$entry->severity->value" />
                    <x-eagle-eye-filament::stat size="meta" label="Actor" :value="$formatter->actorLabel($entry->actor)" />
                    <x-eagle-eye-filament::stat size="meta" label="Subject">
                        @if(null === $entry->subject)
                            —
                        @elseif(null !== $entry->subject->label && '' !== $entry->subject->label)
                            {{ $entry->subject->label }}
                        @else
                            <span title="{{ $entry->subject->id }}">{{ $entry->subject->type }} #{{ $formatter->shortKey($entry->subject->id) }}</span>
                        @endif
                    </x-eagle-eye-filament::stat>
                    <x-eagle-eye-filament::stat size="meta" label="Duration" :value="$formatter->durationLabel($entry->request?->durationMs)" />
                </x-eagle-eye-filament::stat-grid>
            </div>
        </header>

        <div class="ee-detail__columns">
            <div class="ee-stack">
                @if([] !== $changeRows)
                    <x-eagle-eye-filament::card heading="Changes">
                        <x-eagle-eye-filament::diff-table
                            :rows="$changeRows"
                            variant="table"
                            empty-message="No attribute changes recorded for this event."
                        />
                    </x-eagle-eye-filament::card>
                @endif

                <x-eagle-eye-filament::card heading="Payload">
                    <x-eagle-eye-filament::code-block
                        :code="$this->getPayloadJson()"
                        :meta="$this->getPayloadSizeLabel()"
                        copy
                    />
                </x-eagle-eye-filament::card>

                @if([] !== $sealedPaths)
                    <x-eagle-eye-filament::card heading="Sealed values">
                        <div class="ee-stack">
                            @foreach($sealedPaths as $path)
                                <div class="ee-row">
                                    <code class="ee-mono">{{ $path }}</code>
                                    <span class="ee-spacer"></span>
                                    @php($revealed = $this->getRevealed($path))
                                    @if(null !== $revealed)
                                        <code class="ee-mono ee-detail__secret">{{ $revealed }}</code>
                                    @else
                                        <x-filament::button size="sm" color="gray" wire:click="revealSecret('{{ $path }}')" wire:confirm="Reveal this secret? The reveal is written to the audit log.">
                                            🔒 Reveal
                                        </x-filament::button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </x-eagle-eye-filament::card>
                @endif
            </div>

            <div class="ee-stack">
                <x-eagle-eye-filament::card heading="Actor">
                    <div class="ee-row">
                        <span class="ee-detail__avatar" aria-hidden="true">{{ $formatter->initials($entry->actor?->label ?? $formatter->actorLabel($entry->actor)) }}</span>
                        <div class="ee-stack ee-detail__actor">
                            <strong>{{ $formatter->actorLabel($entry->actor) }}</strong>
                            <span class="ee-detail__muted">{{ $entry->actor?->type ?? '—' }}</span>
                        </div>
                        <span class="ee-spacer"></span>
                        @if(null !== $actorUrl)
                            <a class="ee-link" href="{{ $actorUrl }}">View actor profile →</a>
                        @endif
                    </div>
                    @if([] !== $entry->tags)
                        <div class="ee-row ee-detail__tags">
                            @foreach($entry->tags as $tag)
                                <x-eagle-eye-filament::chip tone="tag">{{ $tag }}</x-eagle-eye-filament::chip>
                            @endforeach
                        </div>
                    @endif
                </x-eagle-eye-filament::card>

                @foreach($this->getDetailSections() as $section)
                    <x-eagle-eye-filament::card :heading="$section['title']">
                        <div class="ee-kv">
                            @foreach($section['rows'] as $row)
                                <x-eagle-eye-filament::kv-row :label="$row['label']" :value="$row['value']" :mono="$row['mono']" :type="$row['type'] ?? null" :href="$row['href'] ?? null" :truncated="$row['truncated'] ?? false" />
                            @endforeach
                        </div>
                    </x-eagle-eye-filament::card>
                @endforeach

                <x-eagle-eye-filament::card heading="Trace context">
                    @if(null !== $traceUrl)
                        @if($windowExceeded)
                            <div class="ee-detail__note">Trace has more than 50 entries</div>
                        @endif
                        @php($siblings = $this->getTraceSiblings())
                        @if([] !== $siblings)
                            <div class="ee-stack">
                                @foreach($siblings as $sibling)
                                    <a class="ee-detail__sibling" href="{{ ActivityLogResource::getUrl('view', ['record' => $sibling->id]) }}">
                                        <span class="ee-chip ee-chip--{{ $formatter->channelTone($sibling->channel) }}">{{ $sibling->channel->value }}</span>
                                        <span class="ee-detail__sibling-title">{{ $sibling->title }}</span>
                                        <span class="ee-detail__muted ee-mono">{{ $sibling->loggedAt->format('H:i:s') }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                        <div>
                            <a class="ee-link" href="{{ $traceUrl }}">Open timeline →</a>
                        </div>
                    @else
                        <div class="ee-empty">No trace context.</div>
                    @endif
                </x-eagle-eye-filament::card>

                @if($entry->subject)
                    <x-eagle-eye-filament::card heading="Same subject">
                        @php($related = $this->getRelatedSubjectEntries())
                        @if([] !== $related)
                            <div class="ee-stack">
                                @foreach($related as $relatedEntry)
                                    <a class="ee-detail__sibling" href="{{ ActivityLogResource::getUrl('view', ['record' => $relatedEntry->id]) }}">
                                        <span class="ee-chip ee-chip--{{ $formatter->channelTone($relatedEntry->channel) }}">{{ $relatedEntry->channel->value }}</span>
                                        <span class="ee-detail__sibling-title">{{ $relatedEntry->title }}</span>
                                        <span class="ee-detail__muted ee-mono">{{ $relatedEntry->loggedAt->format('Y-m-d H:i') }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="ee-empty">No other events for this record.</div>
                        @endif
                        @if(null !== $subjectUrl)
                            <div>
                                <a class="ee-link" href="{{ $subjectUrl }}">Full record history →</a>
                            </div>
                        @endif
                    </x-eagle-eye-filament::card>
                @endif
            </div>
        </div>
        </x-eagle-eye-filament::page-shell>
    </div>
</x-filament-panels::page>
