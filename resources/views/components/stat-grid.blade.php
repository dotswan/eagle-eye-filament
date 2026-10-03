@props([
    'plain' => false,
    'variant' => null,
])

<div {{ $attributes->class([
    'ee-stat-grid',
    'ee-stat-grid--plain' => $plain,
    'ee-stat-grid--cards' => 'cards' === $variant,
    'ee-stat-grid--meta' => 'meta' === $variant,
]) }}>{{ $slot }}</div>
