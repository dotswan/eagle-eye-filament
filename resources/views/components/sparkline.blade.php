@props([
    'values' => [],
    'tone' => 'blue',
    'label' => null,
])

@php($tones = ['blue', 'violet', 'amber', 'green', 'gray', 'red', 'rose', 'teal', 'slate'])
@php($resolvedTone = in_array($tone, $tones, true) ? $tone : 'blue')
@php($numbers = array_map(static fn (mixed $value): float => (float) $value, array_values($values)))
@php($maximum = $numbers === [] ? 1.0 : max(1.0, ...$numbers))

<div {{ $attributes->class(['ee-sparkline']) }} role="img" aria-label="{{ $label ?? 'Sparkline' }}">
    @foreach($numbers as $index => $number)
        @php($percent = max(0.0, min(100.0, ($number / $maximum) * 100)))
        <span
            class="ee-sparkline__bar ee-sparkline__bar--{{ $resolvedTone }}"
            style="height: {{ $percent }}%"
            title="{{ $number }}"
            aria-label="{{ $number }}"
        ></span>
    @endforeach
</div>

<table class="ee-sr-only">
    <caption>{{ $label ?? 'Sparkline values' }}</caption>
    <thead>
        <tr><th scope="col">Index</th><th scope="col">Value</th></tr>
    </thead>
    <tbody>
        @foreach($numbers as $index => $number)
            <tr><th scope="row">{{ $index + 1 }}</th><td>{{ $number }}</td></tr>
        @endforeach
    </tbody>
</table>
