@props([
    'tone' => 'neutral',
    'outline' => false,
    'pill' => false,
])

@php($resolvedTone = in_array($tone, ['blue', 'violet', 'amber', 'green', 'gray', 'cyan', 'teal', 'sky', 'red', 'indigo', 'slate', 'neutral', 'rose', 'action', 'tag'], true) ? $tone : 'neutral')

<span {{ $attributes->class(['ee-chip', 'ee-chip--'.$resolvedTone, 'ee-chip--outline' => $outline, 'ee-chip--pill' => $pill]) }}>{{ $slot }}</span>
