@props([
    'active' => '',
    'items' => [],
])

@php($tones = ['blue', 'green', 'amber', 'violet', 'red', 'neutral'])

<nav {{ $attributes->class(['ee-tabs']) }} aria-label="Activity views">
    @foreach($items as $item)
        @php($isActive = ($item['key'] ?? '') === $active)
        @php($tone = in_array($item['tone'] ?? 'neutral', $tones, true) ? ($item['tone'] ?? 'neutral') : 'neutral')
        <a
            href="{{ $item['href'] ?? '#' }}"
            @if($isActive) aria-current="page" @endif
            @class(['ee-tabs__item', 'ee-tabs__item--active' => $isActive])
        >
            <span @class(['ee-tabs__dot', 'ee-tabs__dot--'.$tone => $isActive]) aria-hidden="true"></span>
            <span>{{ $item['label'] ?? '' }}</span>
            @if(null !== ($item['badge'] ?? null) && '' !== $item['badge'])
                <span class="ee-segmented__badge">{{ $item['badge'] }}</span>
            @endif
        </a>
    @endforeach
</nav>
