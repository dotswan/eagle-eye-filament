@if(! $this->facetsLoaded)
    <div class="ee-facets__loading" wire:init="loadFacets">Loading counts…</div>
@else
    <div class="ee-facets__head">{{ $this->getCountWindowLabel() }}</div>

    <div class="ee-facets__group">
        <h3 class="ee-facets__title">Channel</h3>
        @foreach($this->getChannelFacets() as $facet)
            <x-eagle-eye-filament::facet
                :label="$facet['label']"
                :value="$facet['value']"
                :count="$facet['count']"
                wire-model="channels"
                :active="$facet['active']"
            />
        @endforeach
        @if($this->channelCountsUnavailable())
            <p class="ee-facets__note">counts unavailable</p>
        @endif
    </div>

    <div class="ee-facets__group">
        <h3 class="ee-facets__title">Severity</h3>
        @foreach($this->getSeverityFacets() as $facet)
            <x-eagle-eye-filament::facet
                :label="$facet['label']"
                :value="$facet['value']"
                :count="$facet['count']"
                wire-model="severities"
                :active="$facet['active']"
            />
        @endforeach
        @if($this->severityCountsUnavailable())
            <p class="ee-facets__note">counts unavailable</p>
        @endif
    </div>

    <div class="ee-facets__group">
        <h3 class="ee-facets__title">Action</h3>
        @forelse($this->getActionFacets() as $facet)
            <x-eagle-eye-filament::facet
                :label="$facet['label']"
                :value="$facet['value']"
                :count="$facet['count']"
                wire-model="actions"
                :active="$facet['active']"
            />
        @empty
            <p class="ee-facets__note">No actions in this window.</p>
        @endforelse
        @if($this->actionCountsUnavailable())
            <p class="ee-facets__note">counts unavailable</p>
        @endif
    </div>
@endif
