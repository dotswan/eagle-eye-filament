@props([
    'segments' => [],
    'label' => null,
])

@php($tones = ['blue', 'violet', 'amber', 'green', 'gray', 'cyan', 'teal', 'sky', 'red', 'indigo', 'slate', 'neutral', 'rose'])

<div {{ $attributes->class(['ee-bar-stack']) }} role="img" aria-label="{{ $label ?? 'Distribution' }}">
    @foreach($segments as $segment)
        @php($percent = max(0.0, min(100.0, (float) ($segment['percent'] ?? 0))))
        @php($tone = in_array($segment['tone'] ?? 'neutral', $tones, true) ? ($segment['tone'] ?? 'neutral') : 'neutral')
        <span
            class="ee-bar-stack__seg ee-bar-stack__seg--{{ $tone }}"
            style="width: {{ $percent }}%"
            title="{{ $segment['label'] ?? '' }}"
            aria-label="{{ $segment['label'] ?? '' }}"
        ></span>
    @endforeach
</div>
