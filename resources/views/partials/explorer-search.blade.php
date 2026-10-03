<div class="ee-search">
    <form class="ee-search__form" wire:submit="submitSearch" role="search">
        <input
            type="search"
            class="ee-search__input"
            placeholder="Search… e.g. action:model.updated subject:CampaignRequest"
            aria-label="Search activity"
            maxlength="256"
            wire:model="searchInput"
        >
        <button type="submit" class="ee-btn">Search</button>
        <details class="ee-search__help">
            <summary aria-label="Search token help">?</summary>
            <ul>
                <li><code>channel:http</code></li>
                <li><code>severity:&gt;=error</code></li>
                <li><code>action:model.updated</code></li>
                <li><code>actor:user:8821</code></li>
                <li><code>subject:CampaignRequest#42</code></li>
                <li><code>trace:&lt;32 hex&gt;</code></li>
            </ul>
        </details>
    </form>

    @if(null !== $this->getSearchCapabilityMessage())
        <p class="ee-search__hint">{{ $this->getSearchCapabilityMessage() }} Structured tokens still work.</p>
    @endif

    @foreach($this->getSearchErrors() as $error)
        <p class="ee-inline-error" role="alert">{{ $error }}</p>
    @endforeach

    @foreach($this->getSearchWarnings() as $warning)
        <p class="ee-search__warning" role="status">{{ $warning }}</p>
    @endforeach
</div>
