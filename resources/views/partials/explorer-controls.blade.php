@if($this->hasActiveFilters())
    <div class="ee-chips">
        @foreach($this->getFilterChips() as $chip)
            <button
                type="button"
                class="ee-chip ee-chip--neutral"
                wire:click="removeFilter(@js($chip['type']), @js($chip['value']))"
                aria-label="Remove filter {{ $chip['label'] }}"
            >{{ $chip['label'] }} <span aria-hidden="true">×</span></button>
        @endforeach
        <button type="button" class="ee-link" wire:click="resetFilters">Reset filters</button>
    </div>
@endif
