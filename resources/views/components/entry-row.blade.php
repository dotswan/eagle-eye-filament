@use('Dotswan\EagleEyeFilament\Support\Presentation\EntryFormatter')
@use('Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource')
@use('Dotswan\EagleEye\Enums\ActivitySeverity')

@props([
    'entry' => null,
    'selected' => false,
    'href' => null,
    'wire' => null,
    'diffCount' => null,
    'selectId' => null,
])

@php($formatter = app(EntryFormatter::class))
@php($showSeverity = $formatter->isError($entry->severity) || ActivitySeverity::Warning === $entry->severity)
@php($changes = null !== $diffCount ? max(0, (int) $diffCount) : (is_array($entry->payload['changes'] ?? null) ? count($entry->payload['changes']) : 0))
@php($classes = ['ee-entry', 'ee-entry--selected' => (bool) $selected])
@php($detailUrl = ActivityLogResource::getUrl('view', ['record' => $entry->id]))

@if(null !== $selectId)
    <a
        href="{{ $detailUrl }}"
        role="option"
        :aria-selected="selected === @js($selectId)"
        :class="{ 'ee-entry--selected': selected === @js($selectId) }"
        @click="if (wide && ! ($event.metaKey || $event.ctrlKey || $event.shiftKey)) { $event.preventDefault(); selected = @js($selectId); $dispatch('ee-select', { id: @js($selectId) }) }"
        {{ $attributes->class($classes) }}
    >
@elseif(null !== $href)
    <a href="{{ $href }}" role="option" aria-selected="{{ $selected ? 'true' : 'false' }}" @if(null !== $wire) wire:click="{{ $wire }}" @endif {{ $attributes->class($classes) }}>
@else
    <button type="button" role="option" aria-selected="{{ $selected ? 'true' : 'false' }}" @if(null !== $wire) wire:click="{{ $wire }}" @endif {{ $attributes->class($classes) }}>
@endif
        <span class="ee-entry__meta">
            <span class="ee-chip ee-chip--neutral">{{ $entry->channel->value }}</span>
            @if($showSeverity)
                <span class="ee-chip ee-chip--{{ $formatter->severityTone($entry->severity) }} ee-chip--outline">{{ $entry->severity->value }}</span>
            @endif
            <span class="ee-entry__time">{{ $entry->loggedAt->format('H:i:s') }}</span>
        </span>
        <span class="ee-entry__title">{{ $entry->title }}</span>
        <span class="ee-entry__foot">
            <span>{{ $formatter->actorLabel($entry->actor) }}</span>
            @if(null !== $entry->subject?->label && '' !== $entry->subject->label)
                <span aria-hidden="true">·</span>
                <span>{{ $entry->subject->label }}</span>
            @endif
            @if($changes > 0)
                <span aria-hidden="true">·</span>
                <span class="ee-chip ee-chip--neutral">{{ $changes }} {{ 1 === $changes ? 'field' : 'fields' }} changed</span>
            @endif
        </span>
    @if(null !== $selectId)
    </a>
    @elseif(null !== $href)
    </a>
    @else
    </button>
    @endif
