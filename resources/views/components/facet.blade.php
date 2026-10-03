@props([
    'label' => '',
    'value' => '',
    'count' => null,
    'wireModel' => 'channels',
    'active' => false,
])

<label {{ $attributes->class(['ee-facet', 'ee-facet--active' => (bool) $active]) }}>
    <input
        type="checkbox"
        class="ee-facet__input"
        wire:model.live="{{ $wireModel }}"
        value="{{ $value }}"
        @if($active) checked @endif
    >
    <span class="ee-facet__box" aria-hidden="true"></span>
    <span class="ee-facet__label">{{ $label }}</span>
    @if(null !== $count)
        <span class="ee-facet__count">{{ $count }}</span>
    @endif
</label>
