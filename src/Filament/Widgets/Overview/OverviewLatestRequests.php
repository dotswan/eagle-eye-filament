<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Filament\Widgets\Overview;

use DateTimeImmutable;
use Dotswan\EagleEye\Data\ActivityEntry;
use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Enums\SortOrder;
use Dotswan\EagleEyeFilament\Filament\Pages\RecentTracesPage;
use Dotswan\EagleEyeFilament\Filament\Pages\TraceTimelinePage;
use Throwable;

final class OverviewLatestRequests extends OverviewSection
{
    protected string $view = 'eagle-eye-filament::widgets.overview.latest-requests';

    public const int LIMIT = 5;

    public const int EVENT_WINDOW_LEAD_SECONDS = 1;

    public const int EVENT_WINDOW_TAIL_SECONDS = 600;

    /**
     * @var list<array{id: string, traceId: string, url: string, title: string, method: ?string, statusCode: ?int, statusTone: string, durationLabel: string, actor: string, time: string, relativeTime: string, events: ?int}>
     */
    private array $rows = [];

    private bool $resolved = false;

    private bool $failed = false;

    public function backendUnavailable(): bool
    {
        $this->authorizeAccess();
        $this->getRows();

        return $this->failed;
    }

    /**
     * @return list<array{id: string, traceId: string, url: string, title: string, method: ?string, statusCode: ?int, statusTone: string, durationLabel: string, actor: string, time: string, relativeTime: string, events: ?int}>
     */
    public function getRows(): array
    {
        $this->authorizeAccess();

        if ($this->resolved) {
            return $this->rows;
        }

        $this->resolved = true;
        $range = $this->activityRange();
        [$from, $to] = $range->window();

        try {
            $result = $this->repository()->search(
                (new ActivityQuery())
                    ->channels(ActivityChannel::Http)
                    ->between($from, $to)
                    ->orderByTime(SortOrder::Descending)
                    ->limit(self::LIMIT),
            );
        } catch (Throwable) {
            $this->failed = true;

            return $this->rows = [];
        }

        if ([] === $result->entries) {
            return $this->rows = [];
        }

        $counts = $this->eventCounts($result->entries);
        $formatter = $this->formatter();

        foreach ($result->entries as $entry) {
            $durationMs = $entry->request?->durationMs;
            $this->rows[] = [
                'id'            => $entry->id,
                'traceId'       => $entry->trace->traceId,
                'url'           => TraceTimelinePage::getUrl(['trace' => $entry->trace->traceId]),
                'title'         => $entry->title,
                'method'        => $entry->request?->method,
                'statusCode'    => $entry->request?->statusCode,
                'statusTone'    => $this->statusTone($entry->request?->statusCode),
                'durationLabel' => $formatter->durationLabel($durationMs),
                'actor'         => $formatter->actorLabel($entry->actor),
                'time'          => $entry->loggedAt->format('Y-m-d H:i:s'),
                'relativeTime'  => $formatter->relativeTime($entry->loggedAt),
                'events'        => null !== $counts ? ($counts[$entry->trace->traceId] ?? null) : null,
            ];
        }

        return $this->rows;
    }

    public function getTracesUrl(): string
    {
        $this->authorizeAccess();

        return RecentTracesPage::getUrl();
    }

    /**
     * @param  list<ActivityEntry>  $entries
     * @return array<string, int>|null
     */
    private function eventCounts(array $entries): ?array
    {
        $min = $entries[0]->loggedAt;
        $max = $entries[0]->loggedAt;

        foreach ($entries as $entry) {
            if ($entry->loggedAt < $min) {
                $min = $entry->loggedAt;
            }

            if ($entry->loggedAt > $max) {
                $max = $entry->loggedAt;
            }
        }

        try {
            return $this->repository()->aggregate(
                (new ActivityQuery())->between(
                    $this->shift($min, -self::EVENT_WINDOW_LEAD_SECONDS),
                    $this->shift($max, self::EVENT_WINDOW_TAIL_SECONDS),
                ),
                'trace_id',
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function shift(DateTimeImmutable $at, int $seconds): DateTimeImmutable
    {
        return $at->modify(sprintf('%+d seconds', $seconds));
    }

    private function statusTone(?int $statusCode): string
    {
        if (null === $statusCode) {
            return 'gray';
        }

        return match (true) {
            $statusCode >= 500 => 'red',
            $statusCode >= 400 => 'amber',
            default            => 'green',
        };
    }
}
