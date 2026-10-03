<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Filament\Pages;

use DateTimeImmutable;
use Dotswan\EagleEye\Contracts\Security\LogAccessPolicyInterface;
use Dotswan\EagleEye\Data\ActivityEntry;
use Dotswan\EagleEye\Data\RequestSnapshot;
use Dotswan\EagleEye\Data\TraceTree;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEyeFilament\Filament\Concerns\AuthorizesActivityAccess;
use Dotswan\EagleEyeFilament\Filament\Concerns\HasFullContentWidth;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource;
use Dotswan\EagleEyeFilament\Support\Presentation\ActivityNav;
use Dotswan\EagleEyeFilament\Support\Presentation\EntryFormatter;
use Dotswan\EagleEyeFilament\Support\Trace\TraceReader;
use Dotswan\EagleEyeFilament\Support\Trace\TraceSnapshot;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;

final class TraceTimelinePage extends Page
{
    use AuthorizesActivityAccess;
    use HasFullContentWidth;

    public const int MAX_VISUAL_DEPTH = 8;

    public const int MAX_PAYLOAD_PREVIEW = 4096;

    protected string $view = 'eagle-eye-filament::pages.trace-timeline';

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Trace timeline';
    protected static ?string $title = 'Trace timeline';

    #[Locked]
    public string $traceId = '';

    public ?string $selectedId = null;

    private ?TraceSnapshot $snapshot = null;

    private int $totalSpanMs = 1;

    private bool $totalSpanResolved = false;

    public function mount(): void
    {
        abort_unless(app(LogAccessPolicyInterface::class)->canView(auth()->user()), 403);

        $traceId = request()->query('trace');
        abort_unless(is_string($traceId) && 1 === preg_match('/^[a-f0-9]{32}$/', $traceId) && $traceId !== str_repeat('0', 32), 404);

        $this->traceId = $traceId;
    }

    public function getTitle(): string
    {
        $this->authorizeAccess();

        return 'Trace '.$this->traceId;
    }

    public function getHeading(): ?string
    {
        $this->authorizeAccess();

        return null;
    }

    /** @return list<array{key: string, label: string, href: string, tone: string, badge: ?string}> */
    public function getNavItems(): array
    {
        $this->authorizeAccess();

        return ActivityNav::items();
    }

    public function getSnapshot(): TraceSnapshot
    {
        $this->authorizeAccess();

        return $this->snapshot ??= app(TraceReader::class)->read($this->traceId);
    }

    public function traceIsIncomplete(): bool
    {
        $this->authorizeAccess();

        return $this->getSnapshot()->incomplete;
    }

    public function getHeaderTitle(): string
    {
        $this->authorizeAccess();
        $entries = $this->getSnapshot()->entries;

        foreach ($entries as $entry) {
            if (ActivityChannel::Http === $entry->channel) {
                return $entry->title;
            }
        }

        return $entries[0]->title ?? 'Trace';
    }

    public function getSelectedId(): string
    {
        $this->authorizeAccess();
        $ids = $this->loadedIds();

        if (null !== $this->selectedId && in_array($this->selectedId, $ids, true)) {
            return $this->selectedId;
        }

        $resolved = $this->defaultSelectedId($ids);
        $this->selectedId = '' === $resolved ? null : $resolved;

        return $resolved;
    }

    public function getTotalSpanMs(): int
    {
        $this->authorizeAccess();

        if ($this->totalSpanResolved) {
            return $this->totalSpanMs;
        }

        $snapshot = $this->getSnapshot();
        $t0 = $snapshot->t0;

        if ([] === $snapshot->entries || !$t0 instanceof DateTimeImmutable) {
            $this->totalSpanResolved = true;

            return $this->totalSpanMs = 1;
        }

        $lastOffset = 0;
        $span = 0;

        foreach ($snapshot->entries as $entry) {
            $offset = $this->offsetMilliseconds($entry->loggedAt, $t0);
            $lastOffset = max($lastOffset, $offset);
            $span = max($span, $offset + $this->requestDuration($entry));
        }

        $this->totalSpanResolved = true;

        return $this->totalSpanMs = max(1, $lastOffset, $span);
    }

    /**
     * @return list<array{
     *     id: string,
     *     depth: int,
     *     channel: string,
     *     channelTone: string,
     *     severityTone: string,
     *     title: string,
     *     actor: string,
     *     action: string,
     *     offsetLabel: string,
     *     offsetPercent: float,
     *     hasDuration: bool,
     *     widthPercent: float,
     *     durationLabel: ?string,
     *     durationLabelLeft: float,
     *     payloadPreview: string,
     *     eventUrl: string
     * }>
     */
    public function getSpans(): array
    {
        $this->authorizeAccess();
        $snapshot = $this->getSnapshot();

        if ([] === $snapshot->entries) {
            return [];
        }

        $t0 = $snapshot->t0 ?? $snapshot->entries[0]->loggedAt;
        $total = $this->getTotalSpanMs();
        $formatter = app(EntryFormatter::class);
        $spans = [];

        foreach ($this->flatten($snapshot->roots) as $row) {
            $entry = $row['entry'];
            $offsetMs = $this->offsetMilliseconds($entry->loggedAt, $t0);
            $durationMs = $this->requestDuration($entry);
            $hasDuration = ActivityChannel::Http === $entry->channel && $durationMs > 0;
            $offsetPercent = $this->clampPercent(($offsetMs / $total) * 100.0);
            $widthPercent = $hasDuration ? $this->clampPercent(($durationMs / $total) * 100.0) : 0.0;

            $spans[] = [
                'id'                => $entry->id,
                'depth'             => min(self::MAX_VISUAL_DEPTH, $row['depth']),
                'channel'           => $entry->channel->value,
                'channelTone'       => $formatter->channelTone($entry->channel),
                'severityTone'      => $formatter->severityTone($entry->severity),
                'title'             => $entry->title,
                'actor'             => $formatter->actorLabel($entry->actor),
                'action'            => $entry->action,
                'offsetLabel'       => $formatter->offsetLabel($offsetMs * 1000),
                'offsetPercent'     => $offsetPercent,
                'hasDuration'       => $hasDuration,
                'widthPercent'      => $widthPercent,
                'durationLabel'     => $hasDuration ? $formatter->durationLabel($durationMs) : null,
                'durationLabelLeft' => min(88.0, $offsetPercent + $widthPercent),
                'payloadPreview'    => $this->payloadPreview($entry),
                'eventUrl'          => ActivityLogResource::getUrl('view', ['record' => $entry->id]),
            ];
        }

        return $spans;
    }

    /** @return array{duration: string, durationIsSpan: bool, events: int, eventsIncomplete: bool, writes: int, errors: int} */
    public function getStats(): array
    {
        $this->authorizeAccess();
        $snapshot = $this->getSnapshot();
        $formatter = app(EntryFormatter::class);
        $writes = 0;
        $errors = 0;

        foreach ($snapshot->entries as $entry) {
            if (ActivityChannel::Model === $entry->channel) {
                $writes++;
            }

            if ($formatter->isError($entry->severity)) {
                $errors++;
            }
        }

        $rootDuration = $this->rootHttpEntry()?->request?->durationMs;

        if (null !== $rootDuration && $rootDuration > 0) {
            return [
                'duration'         => $formatter->durationLabel($rootDuration),
                'durationIsSpan'   => false,
                'events'           => count($snapshot->entries),
                'eventsIncomplete' => $snapshot->incomplete,
                'writes'           => $writes,
                'errors'           => $errors,
            ];
        }

        $spanMs = 0;
        $t0 = $snapshot->t0;

        if ($t0 instanceof DateTimeImmutable && [] !== $snapshot->entries) {
            $spanMs = $this->offsetMilliseconds($snapshot->entries[count($snapshot->entries) - 1]->loggedAt, $t0);
        }

        return [
            'duration'         => $formatter->durationLabel($spanMs),
            'durationIsSpan'   => true,
            'events'           => count($snapshot->entries),
            'eventsIncomplete' => $snapshot->incomplete,
            'writes'           => $writes,
            'errors'           => $errors,
        ];
    }

    /** @return list<array{label: string, percent: float}> */
    public function getAxisTicks(): array
    {
        $this->authorizeAccess();
        $total = $this->getTotalSpanMs();
        $formatter = app(EntryFormatter::class);
        $ticks = [];

        foreach ([0, 25, 50, 75, 100] as $percent) {
            $ticks[] = [
                'label'   => $formatter->durationLabel((int) round($total * $percent / 100)),
                'percent' => (float) $percent,
            ];
        }

        return $ticks;
    }

    /** @return list<array{channel: string, tone: string}> */
    public function getLegend(): array
    {
        $this->authorizeAccess();
        $formatter = app(EntryFormatter::class);
        $legend = [];

        foreach ($this->getSnapshot()->entries as $entry) {
            $legend[$entry->channel->value] = [
                'channel' => $entry->channel->value,
                'tone'    => $formatter->channelTone($entry->channel),
            ];
        }

        return array_values($legend);
    }

    /** @return list<string> */
    private function loadedIds(): array
    {
        $ids = [];

        foreach ($this->getSnapshot()->entries as $entry) {
            $ids[] = $entry->id;
        }

        return $ids;
    }

    /** @param list<string> $ids */
    private function defaultSelectedId(array $ids): string
    {
        foreach ($this->getSnapshot()->entries as $entry) {
            if (ActivityChannel::Http === $entry->channel && in_array($entry->id, $ids, true)) {
                return $entry->id;
            }
        }

        return $ids[0] ?? '';
    }

    private function rootHttpEntry(): ?ActivityEntry
    {
        foreach ($this->getSnapshot()->entries as $entry) {
            if (ActivityChannel::Http === $entry->channel) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param  list<TraceTree>  $nodes
     * @return list<array{entry: ActivityEntry, depth: int}>
     */
    private function flatten(array $nodes, int $depth = 0): array
    {
        $rows = [];

        foreach ($nodes as $node) {
            $rows[] = ['entry' => $node->entry, 'depth' => $depth];

            if ([] !== $node->children) {
                array_push($rows, ...$this->flatten($node->children, $depth + 1));
            }
        }

        return $rows;
    }

    private function requestDuration(ActivityEntry $entry): int
    {
        $request = $entry->request;

        if (!$request instanceof RequestSnapshot) {
            return 0;
        }

        return $request->durationMs ?? 0;
    }

    private function payloadPreview(ActivityEntry $entry): string
    {
        $json = json_encode(
            app(EntryFormatter::class)->displayPayload($entry->payload),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );

        if (! is_string($json)) {
            return '';
        }

        return mb_strlen($json) > self::MAX_PAYLOAD_PREVIEW ? mb_substr($json, 0, self::MAX_PAYLOAD_PREVIEW).'…' : $json;
    }

    private function offsetMilliseconds(DateTimeImmutable $at, DateTimeImmutable $from): int
    {
        $delta = (float) $at->format('U.u') - (float) $from->format('U.u');

        return max(0, (int) round($delta * 1000));
    }

    private function clampPercent(float $percent): float
    {
        return max(0.0, min(100.0, $percent));
    }
}
