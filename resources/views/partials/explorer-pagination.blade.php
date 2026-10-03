@php
    use Dotswan\EagleEyeFilament\Support\Pagination\ActivityPage;

    $pagerPage = $activityPage ?? null;
    $pagerVariant = $variant ?? 'full';
    $pagerFull = 'full' === $pagerVariant;
    $pagerSupported = $pagerPage instanceof ActivityPage;
    $pagerShown = $pagerSupported && ($pagerPage->hasPrevious || $pagerPage->hasNext || [] !== $pagerPage->entries);
@endphp

@if($pagerShown)
    @php
        $pagerCount = count($pagerPage->entries);
        $pagerStart = $pagerCount > 0 ? ($pagerPage->page - 1) * $pagerPage->perPage + 1 : 0;
        $pagerEnd = $pagerCount > 0 ? $pagerStart + $pagerCount - 1 : 0;
    @endphp

    <div @class(['ee-pager', 'ee-pager--compact' => ! $pagerFull])>
        <span class="ee-pager__range">
            @if($pagerCount > 0)
                Showing <strong>{{ $pagerStart }}–{{ $pagerEnd }}</strong>@if(null !== $pagerPage->count) of {{ $pagerPage->count }}@endif · page {{ $pagerPage->page }}
            @else
                No events on this page
            @endif
        </span>

        <span class="ee-pager__actions">
            @if($pagerFull && method_exists($this, 'setPerPage'))
                <label class="ee-pager__perpage">
                    <span>Per page</span>
                    <select class="ee-pager__select" wire:change="setPerPage($event.target.value)">
                        @foreach([25, 50, 100] as $option)
                            <option value="{{ $option }}" @selected($pagerPage->perPage === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            @if($pagerFull && method_exists($this, 'firstPage') && $pagerPage->page > 2)
                <button type="button" class="ee-link" wire:click="firstPage">Back to newest</button>
            @endif

            <button
                type="button"
                class="ee-btn ee-pager__btn"
                wire:click="previousPage"
                @disabled(! $pagerPage->hasPrevious)
                aria-disabled="{{ $pagerPage->hasPrevious ? 'false' : 'true' }}"
                @if($pagerFull) x-on:click="$nextTick(() => $el.closest('.ee-explorer__list')?.scrollIntoView({ block: 'start' }))" @endif
            >← Newer</button>
            <button
                type="button"
                class="ee-btn ee-pager__btn"
                wire:click="nextPage"
                @disabled(! $pagerPage->hasNext)
                aria-disabled="{{ $pagerPage->hasNext ? 'false' : 'true' }}"
                @if($pagerFull) x-on:click="$nextTick(() => $el.closest('.ee-explorer__list')?.scrollIntoView({ block: 'start' }))" @endif
            >Older →</button>
        </span>
    </div>
@endif
