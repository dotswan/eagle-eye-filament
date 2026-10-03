@use('Dotswan\EagleEyeFilament\Support\Presentation\ActivityNav')
@props([
    'active' => '',
    'breadcrumbs' => [],
    'eyebrow' => null,
    'title' => null,
    'subtitle' => null,
])

<header class="ee-navbar">
    <x-eagle-eye-filament::nav-tabs :active="$active" :items="ActivityNav::items()" />
    @if([] !== $breadcrumbs)
        <x-eagle-eye-filament::breadcrumb :items="$breadcrumbs" />
    @endif
</header>

@if(null !== $eyebrow || null !== $title || null !== $subtitle || isset($actions))
    <div class="ee-page__header">
        <div class="ee-page__header-titles">
            @if(null !== $eyebrow)
                <p class="ee-page__eyebrow">{{ $eyebrow }}</p>
            @endif
            @if(null !== $title)
                <h1 @class(['ee-page__title', 'ee-page__title--overview' => 'overview' === $active])>{{ $title }}</h1>
            @endif
            @if(null !== $subtitle)
                <p class="ee-page__subtitle">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="ee-page__actions">{{ $actions }}</div>
        @endisset
    </div>
@endif

<div class="ee-page__body">{{ $slot }}</div>
