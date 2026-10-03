<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Filament\Widgets\Overview;

use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Data\Series\SeriesWindow;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Enums\ActivitySeverity;
use Dotswan\EagleEye\Enums\Capability;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use Dotswan\EagleEyeFilament\Support\Capabilities;
use Dotswan\EagleEyeFilament\Support\Stats\ActivityRange;
use Throwable;
use DateTimeImmutable;

final class OverviewTraffic extends OverviewSection
{
    protected string $view = 'eagle-eye-filament::widgets.overview.traffic';

    /**
     * @var array{
     *     events: int,
     *     requests: int,
     *     writes: int,
     *     errors: int,
     *     errorRate: string,
     *     perMinute: int,
     *     deltas: array{events: array{0: ?string, 1: ?string}, requests: array{0: ?string, 1: ?string}, errors: array{0: ?string, 1: ?string}, writes: array{0: ?string, 1: ?string}},
     *     sparkTotals: list<int>,
     *     sparkRequests: list<int>,
     *     sparkErrors: list<int>,
     *     sparkWrites: list<int>,
     *     chart: array{labels: list<string>, max: int, bars: list<array{total: int, height: float, segments: list<array{tone: string, label: string, count: int, percent: float}>}>, legend: list<array{label: string, tone: string, total: int}>},
     *     severity: list<array{value: string, label: string, count: int, percent: float, tone: string, url: string}>
     * }|null
     */
    private ?array $data = null;

    private bool $unsupported = false;

    private bool $failed = false;

    public function isSupported(): bool
    {
        return Capabilities::supports(Capability::Aggregation)
            && Capabilities::supports(Capability::SeriesAggregation);
    }

    public function isAvailable(): bool
    {
        $this->authorizeAccess();

        return null !== $this->data();
    }

    public function seriesUnsupported(): bool
    {
        $this->authorizeAccess();

        return ! $this->isSupported();
    }

    public function backendUnavailable(): bool
    {
        $this->authorizeAccess();
        $this->data();

        return $this->failed;
    }

    /** @return list<array{key: string, label: string, value: string, unit: ?string, delta: ?string, deltaTone: ?string, spark: list<int>, sparkTone: string}> */
    public function getKpis(): array
    {
        $this->authorizeAccess();
        $data = $this->data();

        if (null === $data) {
            return [];
        }

        return [
            ['key' => 'events', 'label' => 'Events', 'value' => (string) $data['events'], 'unit' => null, 'delta' => $data['deltas']['events'][0], 'deltaTone' => $data['deltas']['events'][1], 'spark' => $data['sparkTotals'], 'sparkTone' => 'blue'],
            ['key' => 'requests', 'label' => 'Requests', 'value' => (string) $data['requests'], 'unit' => null, 'delta' => $data['deltas']['requests'][0], 'deltaTone' => $data['deltas']['requests'][1], 'spark' => $data['sparkRequests'], 'sparkTone' => 'blue'],
            ['key' => 'error_rate', 'label' => 'Error rate', 'value' => $data['errorRate'], 'unit' => null, 'delta' => $data['deltas']['errors'][0], 'deltaTone' => $data['deltas']['errors'][1], 'spark' => $data['sparkErrors'], 'sparkTone' => 'red'],
            ['key' => 'writes', 'label' => 'Writes', 'value' => (string) $data['writes'], 'unit' => null, 'delta' => $data['deltas']['writes'][0], 'deltaTone' => $data['deltas']['writes'][1], 'spark' => $data['sparkWrites'], 'sparkTone' => 'violet'],
        ];
    }

    /** @return array{labels: list<string>, max: int, bars: list<array{total: int, height: float, segments: list<array{tone: string, label: string, count: int, percent: float}>}>, legend: list<array{label: string, tone: string, total: int}>} */
    public function getChannelChart(): array
    {
        $this->authorizeAccess();
        $data = $this->data();

        return $data['chart'] ?? ['labels' => [], 'max' => 0, 'bars' => [], 'legend' => []];
    }

    /** @return list<array{value: string, label: string, count: int, percent: float, tone: string, url: string}> */
    public function getSeverityMix(): array
    {
        $this->authorizeAccess();
        $data = $this->data();

        return $data['severity'] ?? [];
    }

    public function getEventsTotal(): int
    {
        $this->authorizeAccess();

        return $this->data()['events'] ?? 0;
    }

    public function getRequestsTotal(): int
    {
        $this->authorizeAccess();

        return $this->data()['requests'] ?? 0;
    }

    public function getWritesTotal(): int
    {
        $this->authorizeAccess();

        return $this->data()['writes'] ?? 0;
    }

    public function getErrorRateLabel(): string
    {
        $this->authorizeAccess();

        return $this->data()['errorRate'] ?? '—';
    }

    public function getPerMinute(): ?int
    {
        $this->authorizeAccess();

        return $this->data()['perMinute'] ?? null;
    }

    public function getEventsDelta(): ?string
    {
        $this->authorizeAccess();

        return $this->data()['deltas']['events'][0] ?? null;
    }

    /**
     * @return array{
     *     events: int,
     *     requests: int,
     *     writes: int,
     *     errors: int,
     *     errorRate: string,
     *     perMinute: int,
     *     deltas: array{events: array{0: ?string, 1: ?string}, requests: array{0: ?string, 1: ?string}, errors: array{0: ?string, 1: ?string}, writes: array{0: ?string, 1: ?string}},
     *     sparkTotals: list<int>,
     *     sparkRequests: list<int>,
     *     sparkErrors: list<int>,
     *     sparkWrites: list<int>,
     *     chart: array{labels: list<string>, max: int, bars: list<array{total: int, height: float, segments: list<array{tone: string, label: string, count: int, percent: float}>}>, legend: list<array{label: string, tone: string, total: int}>},
     *     severity: list<array{value: string, label: string, count: int, percent: float, tone: string, url: string}>
     * }|null
     */
    private function data(): ?array
    {
        if ($this->failed || $this->unsupported) {
            return null;
        }

        if (is_array($this->data)) {
            return $this->data;
        }

        if (! $this->isSupported()) {
            $this->unsupported = true;

            return null;
        }

        $range = $this->activityRange();
        [$from, $to] = $range->window();
        $step = $range->stepSeconds();
        $window = SeriesWindow::fromQuery((new ActivityQuery())->between($from, $to), $step);
        $previousTo = $window->from;
        $previousFrom = $previousTo->modify(sprintf('-%d seconds', $window->to->getTimestamp() - $window->from->getTimestamp()));

        try {
            $channelSeries = $this->repository()->aggregateSeries(
                (new ActivityQuery())->between($from, $to),
                'channel',
                $step,
            );
            $severitySeries = $this->repository()->aggregateSeries(
                (new ActivityQuery())->between($from, $to),
                'severity',
                $step,
            );
            $previousChannels = $this->repository()->aggregate((new ActivityQuery())->between($previousFrom, $previousTo), 'channel');
            $previousSeverities = $this->repository()->aggregate((new ActivityQuery())->between($previousFrom, $previousTo), 'severity');
        } catch (Throwable) {
            $this->failed = true;

            return null;
        }

        $channelTotals = $this->seriesTotals($channelSeries['series']);
        $severityTotals = $this->seriesTotals($severitySeries['series']);
        $events = array_sum($channelTotals);
        $requests = $channelTotals[ActivityChannel::Http->value] ?? 0;
        $writes = $channelTotals[ActivityChannel::Model->value] ?? 0;
        $errors = $this->errorTotal($severityTotals);
        $previousEvents = array_sum($previousChannels);
        $previousRequests = $previousChannels[ActivityChannel::Http->value] ?? 0;
        $previousWrites = $previousChannels[ActivityChannel::Model->value] ?? 0;
        $previousErrors = $this->errorTotal($previousSeverities);

        $sparkTotals = $this->bucketTotals($channelSeries['series']);
        $sparkRequests = $this->bucketTotals([$channelSeries['series'][ActivityChannel::Http->value] ?? []]);
        $sparkErrors = $this->bucketTotals($this->errorSeries($severitySeries['series']));
        $sparkWrites = $this->bucketTotals([$channelSeries['series'][ActivityChannel::Model->value] ?? []]);

        $lastCompleteBucket = count($sparkTotals) >= 2 ? $sparkTotals[count($sparkTotals) - 2] : ($sparkTotals[0] ?? 0);
        $perMinute = ActivityRange::Hour === $range
            ? (int) round($events / 60)
            : (int) round($lastCompleteBucket * 60 / $step);

        return $this->data = [
            'events'    => $events,
            'requests'  => $requests,
            'writes'    => $writes,
            'errors'    => $errors,
            'errorRate' => $events > 0 ? $this->trimNumber($errors / $events * 100).'%' : '—',
            'perMinute' => max(0, $perMinute),
            'deltas'    => [
                'events'   => $this->delta($events, $previousEvents),
                'requests' => $this->delta($requests, $previousRequests),
                'errors'   => $this->rateDelta($errors, $events, $previousErrors, $previousEvents),
                'writes'   => $this->delta($writes, $previousWrites),
            ],
            'sparkTotals'   => $sparkTotals,
            'sparkRequests' => $sparkRequests,
            'sparkErrors'   => $sparkErrors,
            'sparkWrites'   => $sparkWrites,
            'chart'         => $this->buildChart($channelSeries['series'], $channelSeries['buckets'], $range),
            'severity'      => $this->buildSeverityMix($severityTotals),
        ];
    }

    /**
     * @param  array<string, list<int>>  $series
     * @return array<string, int>
     */
    private function seriesTotals(array $series): array
    {
        $totals = [];

        foreach ($series as $group => $values) {
            $totals[(string) $group] = array_sum($values);
        }

        return $totals;
    }

    /**
     * @param  array<array-key, list<int>>  $series
     * @return list<int>
     */
    private function bucketTotals(array $series): array
    {
        $length = 0;

        foreach ($series as $values) {
            $length = max($length, count($values));
        }

        $totals = array_fill(0, $length, 0);

        foreach ($series as $values) {
            foreach ($values as $index => $value) {
                if (array_key_exists($index, $totals)) {
                    $totals[$index] += (int) $value;
                }
            }
        }

        return $totals;
    }

    /**
     * @param  array<string, list<int>>  $severitySeries
     * @return array<string, list<int>>
     */
    private function errorSeries(array $severitySeries): array
    {
        $errors = [];

        foreach ($this->errorSeverities() as $value) {
            if (isset($severitySeries[$value])) {
                $errors[$value] = $severitySeries[$value];
            }
        }

        return $errors;
    }

    /** @param array<string, int> $severityTotals */
    private function errorTotal(array $severityTotals): int
    {
        $total = 0;

        foreach ($this->errorSeverities() as $value) {
            $total += $severityTotals[$value] ?? 0;
        }

        return $total;
    }

    /** @return list<string> */
    private function errorSeverities(): array
    {
        return [
            ActivitySeverity::Error->value,
            ActivitySeverity::Critical->value,
            ActivitySeverity::Alert->value,
            ActivitySeverity::Emergency->value,
        ];
    }

    /**
     * @param  array<string, list<int>>  $series
     * @param  list<DateTimeImmutable>  $buckets
     * @return array{labels: list<string>, max: int, bars: list<array{total: int, height: float, segments: list<array{tone: string, label: string, count: int, percent: float}>}>, legend: list<array{label: string, tone: string, total: int}>}
     */
    private function buildChart(array $series, array $buckets, ActivityRange $range): array
    {
        $channelTotals = $this->seriesTotals($series);
        arsort($channelTotals);

        $top = array_map(static fn (int|string $value): string => (string) $value, array_slice(array_keys($channelTotals), 0, 4));
        $topSet = array_flip($top);
        $otherTotal = 0;

        foreach ($channelTotals as $value => $total) {
            if (! isset($topSet[$value])) {
                $otherTotal += $total;
            }
        }

        $legend = [];

        foreach ($top as $value) {
            $legend[] = ['label' => (string) $value, 'tone' => $this->channelTone((string) $value), 'total' => $channelTotals[$value]];
        }

        if ($otherTotal > 0) {
            $legend[] = ['label' => 'other', 'tone' => 'neutral', 'total' => $otherTotal];
        }

        $bucketTotals = $this->bucketTotals($series);
        $max = [] === $bucketTotals ? 0 : max($bucketTotals);
        $bars = [];

        foreach (array_keys($buckets) as $index) {
            $bucketTotal = $bucketTotals[$index] ?? 0;
            $segments = [];

            foreach ($top as $value) {
                $count = (int) ($series[$value][$index] ?? 0);
                $segments[] = [
                    'tone'    => $this->channelTone((string) $value),
                    'label'   => (string) $value,
                    'count'   => $count,
                    'percent' => $this->percent($count, $bucketTotal),
                ];
            }

            if ($otherTotal > 0) {
                $otherCount = 0;

                foreach ($channelTotals as $value => $total) {
                    if (! isset($topSet[$value])) {
                        $otherCount += (int) ($series[$value][$index] ?? 0);
                    }
                }

                $segments[] = ['tone' => 'neutral', 'label' => 'other', 'count' => $otherCount, 'percent' => $this->percent($otherCount, $bucketTotal)];
            }

            $bars[] = [
                'total'    => $bucketTotal,
                'height'   => $this->percent($bucketTotal, $max),
                'segments' => $segments,
            ];
        }

        return [
            'labels' => $this->axisLabels($buckets, $range),
            'max'    => $max,
            'bars'   => $bars,
            'legend' => $legend,
        ];
    }

    /**
     * @param  list<DateTimeImmutable>  $buckets
     * @return list<string>
     */
    private function axisLabels(array $buckets, ActivityRange $range): array
    {
        $count = count($buckets);

        if (0 === $count) {
            return [];
        }

        $indices = array_unique([0, intdiv($count - 1, 3), intdiv(2 * ($count - 1), 3), $count - 1]);
        sort($indices);
        $labels = [];

        foreach ($indices as $index) {
            $labels[] = $buckets[$index]->format($range->axisFormat());
        }

        return $labels;
    }

    /**
     * @param  array<string, int>  $severityTotals
     * @return list<array{value: string, label: string, count: int, percent: float, tone: string, url: string}>
     */
    private function buildSeverityMix(array $severityTotals): array
    {
        $total = array_sum($severityTotals);
        $rows = [];

        foreach (ActivitySeverity::cases() as $severity) {
            $count = $severityTotals[$severity->value] ?? 0;

            if ($count <= 0) {
                continue;
            }

            $rows[] = [
                'value'   => $severity->value,
                'label'   => $severity->value,
                'count'   => $count,
                'percent' => $this->percent($count, $total),
                'tone'    => $this->formatter()->severityTone($severity),
                'url'     => ListActivityLogs::getUrl(['severity' => [$severity->value]]),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => $right['count'] <=> $left['count']);

        return $rows;
    }

    private function percent(int $value, int $total): float
    {
        if ($total <= 0 || $value <= 0) {
            return 0.0;
        }

        return max(0.0, min(100.0, $value / $total * 100));
    }

    private function channelTone(string $value): string
    {
        $channel = ActivityChannel::tryFrom($value);

        return $channel instanceof ActivityChannel ? $this->formatter()->channelTone($channel) : 'neutral';
    }
}
