@props([
    'label' => null,
    'value' => null,
    'unit' => null,
    'delta' => null,
    'deltaTone' => 'neutral',
    'size' => 'summary',
    'mono' => null,
])

@php
    $mono ??= 'meta' !== $size;
@endphp

<div {{ $attributes->class(['ee-stat', 'ee-stat--'.$size]) }}>
    <div class="ee-stat__head">
        @if(null !== $label)
            <span class="ee-stat__label">{{ $label }}</span>
        @endif
        @if(null !== $delta)
            <span @class([
                'ee-stat__delta',
                'ee-stat__delta--good' => 'good' === $deltaTone,
                'ee-stat__delta--bad' => 'bad' === $deltaTone,
                'ee-stat__delta--neutral' => 'neutral' === $deltaTone,
            ])>{{ $delta }}</span>
        @endif
    </div>
    <div class="ee-stat__body">
        <span @class(['ee-stat__value', 'ee-stat__value--mono' => $mono])>{{ $value }}</span>
        @if(null !== $unit)
            <span class="ee-stat__unit">{{ $unit }}</span>
        @endif
    </div>
    @if(! $slot->isEmpty())
        <div>{{ $slot }}</div>
    @endif
</div>
