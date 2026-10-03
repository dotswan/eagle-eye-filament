@props([
    'items' => [],
])

<nav {{ $attributes->class(['ee-breadcrumb']) }} aria-label="Breadcrumb">
    @foreach($items as $index => $item)
        @if($index > 0)
            <span class="ee-breadcrumb__sep" aria-hidden="true">/</span>
        @endif
        @if(isset($item['href']) && '' !== $item['href'])
            <a class="ee-breadcrumb__item" href="{{ $item['href'] }}">{{ $item['label'] ?? '' }}</a>
        @else
            <span class="ee-breadcrumb__item ee-breadcrumb__current" aria-current="page">{{ $item['label'] ?? '' }}</span>
        @endif
    @endforeach
</nav>
