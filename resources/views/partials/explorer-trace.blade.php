@php($groups = $this->getTraceGroups())

@if([] === $groups)
    <x-eagle-eye-filament::empty title="No traces" message="No activity matches these filters." />
@else
    <div class="ee-list" role="listbox" aria-label="Activity traces" x-data="{ selected: null }">
        @foreach($groups as $group)
            <div class="ee-group" x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }">
                <div class="ee-group__header">
                    <button
                        type="button"
                        class="ee-group__caret"
                        @click="open = !open"
                        :aria-expanded="open ? 'true' : 'false'"
                    ><span x-show="open">▾</span><span x-show="!open">▸</span></button>
                    <span class="ee-status-dot ee-status-dot--{{ $group['statusTone'] }}" aria-hidden="true"></span>
                    <span class="ee-group__title">{{ $group['header']->title }}</span>
                    <span class="ee-group__meta">{{ $group['count'] }} on this page · {{ $group['actor'] }}</span>
                    <span class="ee-spacer"></span>
                    <a class="ee-link" href="{{ $this->getTraceUrl($group['traceId']) }}">Open timeline →</a>
                </div>

                <div class="ee-group__spans" aria-hidden="true">
                    @foreach($group['entries'] as $row)
                        <span class="ee-group__span ee-group__span--{{ $row['tone'] }}" style="left: {{ $row['percent'] }}%"></span>
                    @endforeach
                </div>

                <div class="ee-group__children" x-show="open" x-cloak>
                    @foreach($group['entries'] as $row)
                        @if($row['entry']->id !== $group['header']->id)
                            <button
                                type="button"
                                role="option"
                                :aria-selected="selected === @js($row['entry']->id)"
                                class="ee-group__child"
                                @click="selected = @js($row['entry']->id); $dispatch('ee-select', { id: @js($row['entry']->id) })"
                            >
                                <span class="ee-group__offset">{{ $row['offset'] }}</span>
                                <span class="ee-chip ee-chip--{{ $row['tone'] }}">{{ $row['entry']->channel->value }}</span>
                                <span class="ee-group__child-title">{{ $row['entry']->title }}</span>
                            </button>
                        @endif
                    @endforeach
                </div>

                @if($group['partial'])
                    <div class="ee-group__partial">
                        May continue on other pages ·
                        <a class="ee-link" href="{{ $this->getTraceUrl($group['traceId']) }}">Open timeline →</a>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    @include('eagle-eye-filament::partials.explorer-pagination', ['activityPage' => $this->getActivityPage(), 'variant' => 'full'])
@endif
