@props([
    'heading' => null,
    'subheading' => null,
    'flush' => false,
])

<section {{ $attributes->class(['ee-card']) }}>
    @if(null !== $heading || null !== $subheading || isset($actions))
        <header class="ee-card__head">
            <div class="ee-card__titles">
                @if(null !== $heading)
                    <h2 class="ee-card__title">{{ $heading }}</h2>
                @endif
                @if(null !== $subheading)
                    <div class="ee-card__sub">{{ $subheading }}</div>
                @endif
            </div>
            @isset($actions)
                <div class="ee-card__actions">{{ $actions }}</div>
            @endisset
        </header>
    @endif
    <div @class(['ee-card__body', 'ee-card__body--flush' => $flush])>{{ $slot }}</div>
</section>
