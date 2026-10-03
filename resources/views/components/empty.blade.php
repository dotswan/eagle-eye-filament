@props([
    'message' => null,
    'title' => null,
])

<div {{ $attributes->class(['ee-empty']) }}>
    @if(null !== $title)
        <strong>{{ $title }}</strong>
    @endif
    @if(null !== $message)
        <span>{{ $message }}</span>
    @else
        <span>{{ $slot }}</span>
    @endif
</div>
