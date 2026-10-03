@props([
    'rows' => [],
    'variant' => 'table',
    'emptyMessage' => 'No changes recorded.',
])

@if('stacked' === $variant)
    <div {{ $attributes->class(['ee-diff--stacked']) }}>
        @forelse($rows as $row)
            <div class="ee-diff__block">
                <div class="ee-diff__block-head">{{ $row['label'] ?? $row['field'] ?? '' }}</div>

                @if([] !== ($row['paths'] ?? []))
                    @foreach($row['paths'] as $path)
                        <div class="ee-diff__line ee-diff__line--before">
                            <span class="ee-diff__sign" aria-hidden="true">−</span>
                            <span><code>{{ $path['path'] }}</code> {{ $path['before'] }}</span>
                        </div>
                        <div class="ee-diff__line ee-diff__line--after">
                            <span class="ee-diff__sign" aria-hidden="true">+</span>
                            <span><code>{{ $path['path'] }}</code> {{ $path['after'] }}</span>
                        </div>
                    @endforeach
                    @if($row['formattingOnly'] ?? false)
                        <div class="ee-empty">Formatting only</div>
                    @endif
                    @if(! empty($row['beforeFull']))
                        <details class="ee-diff__more">
                            <summary>show all</summary>
                            <pre class="ee-diff__full">{{ $row['beforeFull'] }}</pre>
                        </details>
                    @endif
                @else
                    <div class="ee-diff__line ee-diff__line--before">
                        <span class="ee-diff__sign" aria-hidden="true">−</span>
                        <span>{{ $row['before'] ?? '' }}</span>
                    </div>
                    @if(! empty($row['beforeFull']))
                        <details class="ee-diff__more">
                            <summary>show all</summary>
                            <pre class="ee-diff__full">{{ $row['beforeFull'] }}</pre>
                        </details>
                    @endif
                    <div class="ee-diff__line ee-diff__line--after">
                        <span class="ee-diff__sign" aria-hidden="true">+</span>
                        <span>{{ $row['after'] ?? '' }}</span>
                    </div>
                @endif

                @if(! empty($row['afterFull']))
                    <details class="ee-diff__more">
                        <summary>show all</summary>
                        <pre class="ee-diff__full">{{ $row['afterFull'] }}</pre>
                    </details>
                @endif

                @if($row['truncated'] ?? false)
                    <div class="ee-empty">More paths changed…</div>
                @endif
            </div>
        @empty
            <div class="ee-empty">{{ $emptyMessage }}</div>
        @endforelse
    </div>
@else
    <div {{ $attributes->class(['ee-diff--table']) }}>
        <div class="ee-diff__head">Field</div>
        <div class="ee-diff__head">Before</div>
        <div class="ee-diff__head" aria-hidden="true"></div>
        <div class="ee-diff__head">After</div>
        @forelse($rows as $row)
            @if([] !== ($row['paths'] ?? []))
                @foreach($row['paths'] as $path)
                    <div class="ee-diff__field"><code>{{ $row['label'] ?? '' }}</code> · <code>{{ $path['path'] }}</code></div>
                    <div class="ee-diff__cell"><span class="ee-diff__before">{{ $path['before'] }}</span></div>
                    <div class="ee-diff__arrow" aria-hidden="true">→</div>
                    <div class="ee-diff__cell"><span class="ee-diff__after">{{ $path['after'] }}</span></div>
                @endforeach
                @if($row['formattingOnly'] ?? false)
                    <div class="ee-empty ee-diff__empty">Formatting only</div>
                @endif
                @if($row['truncated'] ?? false)
                    <div class="ee-empty ee-diff__empty">More paths changed…</div>
                @endif
            @else
                <div class="ee-diff__field">{{ $row['label'] ?? $row['field'] ?? '' }}</div>
                <div class="ee-diff__cell">
                    <span class="ee-diff__before">{{ $row['before'] ?? '' }}</span>
                    @if(! empty($row['beforeFull']))
                        <details class="ee-diff__more">
                            <summary>show all</summary>
                            <pre class="ee-diff__full">{{ $row['beforeFull'] }}</pre>
                        </details>
                    @endif
                </div>
                <div class="ee-diff__arrow" aria-hidden="true">→</div>
                <div class="ee-diff__cell">
                    <span class="ee-diff__after">{{ $row['after'] ?? '' }}</span>
                    @if(! empty($row['afterFull']))
                        <details class="ee-diff__more">
                            <summary>show all</summary>
                            <pre class="ee-diff__full">{{ $row['afterFull'] }}</pre>
                        </details>
                    @endif
                </div>
            @endif
        @empty
            <div class="ee-empty ee-diff__empty">{{ $emptyMessage }}</div>
        @endforelse
    </div>
@endif
