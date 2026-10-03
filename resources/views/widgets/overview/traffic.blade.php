<div class="ee-section">
    @if($this->seriesUnsupported())
        <x-eagle-eye-filament::card heading="Traffic" subheading="Events, requests, error rate and writes">
            <x-eagle-eye-filament::empty message="Not supported by the active log backend" />
        </x-eagle-eye-filament::card>
    @elseif($this->backendUnavailable())
        <x-eagle-eye-filament::card heading="Traffic" subheading="Events, requests, error rate and writes">
            <x-eagle-eye-filament::empty message="Backend unavailable, try a narrower time range" />
        </x-eagle-eye-filament::card>
    @else
        @php($kpis = $this->getKpis())
        @php($chart = $this->getChannelChart())
        @php($severity = $this->getSeverityMix())

        <x-eagle-eye-filament::card heading="Traffic" subheading="Events, requests, error rate and writes for the selected range">
            <div class="ee-row">
                <x-eagle-eye-filament::chip tone="neutral" outline>≈{{ $this->getPerMinute() }} events/min · last full hour</x-eagle-eye-filament::chip>
            </div>
            <div class="ee-overview__kpis">
                <x-eagle-eye-filament::stat-grid variant="cards">
                    @foreach($kpis as $kpi)
                        <x-eagle-eye-filament::stat
                            size="kpi"
                            :label="$kpi['label']"
                            :value="$kpi['value']"
                            :unit="$kpi['unit']"
                            :delta="$kpi['delta']"
                            :deltaTone="$kpi['deltaTone']"
                        >
                            <x-eagle-eye-filament::sparkline :values="$kpi['spark']" :tone="$kpi['sparkTone']" :label="$kpi['label'].' trend'" />
                        </x-eagle-eye-filament::stat>
                    @endforeach
                </x-eagle-eye-filament::stat-grid>
            </div>
        </x-eagle-eye-filament::card>

        <div class="ee-overview__row2">
            <x-eagle-eye-filament::card heading="Events by channel" subheading="Stacked per bucket, top four channels">
            @if([] === $chart['bars'])
                <x-eagle-eye-filament::empty message="No activity in the selected range." />
            @else
                <div class="ee-chart">
                    <div class="ee-chart__bars" role="img" aria-label="Events by channel per time bucket">
                        @foreach($chart['bars'] as $bar)
                            <div class="ee-chart__bar" style="height: {{ max(0.0, min(100.0, (float) $bar['height'])) }}%">
                                @foreach($bar['segments'] as $segment)
                                    <span
                                        class="ee-chart__seg ee-chart__seg--{{ $segment['tone'] }}"
                                        style="height: {{ max(0.0, min(100.0, (float) $segment['percent'])) }}%"
                                        title="{{ $segment['label'] }}: {{ $segment['count'] }}"
                                        aria-label="{{ $segment['label'] }}: {{ $segment['count'] }} events"
                                    ></span>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <div class="ee-chart__axis" aria-hidden="true">
                        @foreach($chart['labels'] as $label)
                            <span>{{ $label }}</span>
                        @endforeach
                    </div>
                    <div class="ee-chart__legend">
                        @foreach($chart['legend'] as $item)
                            <span class="ee-chart__legend-item">
                                <span class="ee-chart__swatch ee-chart__seg--{{ $item['tone'] }}"></span>
                                {{ $item['label'] }} · {{ $item['total'] }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <table class="ee-sr-only">
                    <caption>Events by channel per bucket</caption>
                    <thead>
                        <tr><th scope="col">Bucket</th><th scope="col">Total events</th></tr>
                    </thead>
                    <tbody>
                        @foreach($chart['bars'] as $index => $bar)
                            <tr>
                                <th scope="row">{{ $chart['labels'][$index] ?? ($index + 1) }}</th>
                                <td>{{ $bar['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-eagle-eye-filament::card>

        <x-eagle-eye-filament::card heading="Severity mix" subheading="Share of events in the selected range">
            @if([] === $severity)
                <x-eagle-eye-filament::empty message="No activity in the selected range." />
            @else
                <div class="ee-bar-stack" role="img" aria-label="Severity mix">
                    @foreach($severity as $row)
                        <span
                            class="ee-bar-stack__seg ee-bar-stack__seg--{{ $row['tone'] }}"
                            style="width: {{ max(0.0, min(100.0, (float) $row['percent'])) }}%"
                            title="{{ $row['label'] }}: {{ $row['count'] }}"
                            aria-label="{{ $row['label'] }}: {{ $row['count'] }} events"
                        ></span>
                    @endforeach
                </div>
                <div class="ee-sev">
                    @foreach($severity as $row)
                        <a class="ee-sev__row" href="{{ $row['url'] }}">
                            <x-eagle-eye-filament::chip :tone="$row['tone']">{{ $row['label'] }}</x-eagle-eye-filament::chip>
                            <span class="ee-sev__track">
                                <span class="ee-sev__fill ee-chart__seg--{{ $row['tone'] }}" style="width: {{ max(0.0, min(100.0, (float) $row['percent'])) }}%"></span>
                            </span>
                            <span class="ee-sev__count ee-mono">{{ $row['count'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </x-eagle-eye-filament::card>
        </div>
    @endif
</div>
