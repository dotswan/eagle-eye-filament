@props([
    'items' => [],
])

<div {{ $attributes->class(['ee-segmented']) }} role="tablist">
    @foreach($items as $item)
        @php($isActive = (bool) ($item['active'] ?? false))
        @php($isMuted = (bool) ($item['muted'] ?? false))
        @php($hasDot = $isActive || (bool) ($item['dot'] ?? false))
        @php($tone = in_array($item['tone'] ?? 'neutral', ['blue', 'green', 'amber', 'violet', 'red', 'neutral'], true) ? ($item['tone'] ?? 'neutral') : 'neutral')

        @if(isset($item['href']) && '' !== $item['href'])
            <a
                href="{{ $item['href'] }}"
                role="tab"
                aria-selected="{{ $isActive ? 'true' : 'false' }}"
                @if(null !== ($item['title'] ?? null)) title="{{ $item['title'] }}" @endif
                @class(['ee-segmented__item', 'ee-segmented__item--active' => $isActive, 'ee-segmented__item--muted' => $isMuted])
            >
                @if($hasDot)
                    <span class="ee-tabs__dot ee-tabs__dot--{{ $tone }}" aria-hidden="true"></span>
                @endif
                <span>{{ $item['label'] ?? '' }}</span>
                @if(null !== ($item['badge'] ?? null) && '' !== $item['badge'])
                    <span class="ee-segmented__badge">{{ $item['badge'] }}</span>
                @endif
            </a>
        @else
            <button
                type="button"
                role="tab"
                aria-selected="{{ $isActive ? 'true' : 'false' }}"
                @if(null !== ($item['wire'] ?? null)) wire:click="{{ $item['wire'] }}" @endif
                @if(null !== ($item['title'] ?? null)) title="{{ $item['title'] }}" @endif
                @class(['ee-segmented__item', 'ee-segmented__item--active' => $isActive, 'ee-segmented__item--muted' => $isMuted])
            >
                @if($hasDot)
                    <span class="ee-tabs__dot ee-tabs__dot--{{ $tone }}" aria-hidden="true"></span>
                @endif
                <span>{{ $item['label'] ?? '' }}</span>
                @if(null !== ($item['badge'] ?? null) && '' !== $item['badge'])
                    <span class="ee-segmented__badge">{{ $item['badge'] }}</span>
                @endif
            </button>
        @endif
    @endforeach
</div>
