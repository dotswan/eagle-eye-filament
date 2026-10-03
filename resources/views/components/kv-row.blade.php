@props([
    'label' => '',
    'value' => null,
    'mono' => false,
    'type' => null,
    'href' => null,
    'truncated' => false,
])

@if('code' === $type)
    <details {{ $attributes->class(['ee-kv__row', 'ee-kv__row--code']) }}>
        <summary class="ee-kv__label">{{ $label }}</summary>
        <x-eagle-eye-filament::code-block :code="$value" />
        @if($truncated)
            <p class="ee-kv__note">Truncated to fit the capture limit.</p>
        @endif
    </details>
@else
    <div {{ $attributes->class(['ee-kv__row']) }}>
        <span class="ee-kv__label">{{ $label }}</span>
        <span @class(['ee-kv__value', 'ee-kv__value--mono' => $mono])>
            @if(! $slot->isEmpty())
                {{ $slot }}
            @elseif('link' === $type && null !== $href)
                <a class="ee-link" href="{{ $href }}">{{ $value }}</a>
            @else
                {{ $value }}
            @endif
        </span>
    </div>
@endif
