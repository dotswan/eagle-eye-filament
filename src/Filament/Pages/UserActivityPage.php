<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Filament\Pages;

use DateTimeImmutable;
use DateTimeZone;
use Dotswan\EagleEye\Contracts\Repository\ActivityRepositoryInterface;
use Dotswan\EagleEye\Contracts\Resolution\ActivityIdentityResolverInterface;
use Dotswan\EagleEye\Contracts\Security\LogAccessPolicyInterface;
use Dotswan\EagleEye\Data\ActivityEntry;
use Dotswan\EagleEye\Data\Actor;
use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Enums\ActivitySeverity;
use Dotswan\EagleEye\Enums\Capability;
use Dotswan\EagleEye\Enums\SortOrder;
use Dotswan\EagleEyeFilament\Filament\Concerns\AuthorizesActivityAccess;
use Dotswan\EagleEyeFilament\Filament\Concerns\HasFullContentWidth;
use Dotswan\EagleEyeFilament\Support\Capabilities;
use Dotswan\EagleEyeFilament\Support\Pagination\ActivityPage;
use Dotswan\EagleEyeFilament\Support\Pagination\ActivityPageLoader;
use Dotswan\EagleEyeFilament\Support\Pagination\CursorHistory;
use Dotswan\EagleEyeFilament\Support\Presentation\ActivityNav;
use Dotswan\EagleEyeFilament\Support\Presentation\EntryFormatter;
use Filament\Pages\Page;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use JsonException;
use Livewire\Attributes\Locked;
use Throwable;

final class UserActivityPage extends Page
{
    use AuthorizesActivityAccess;
    use HasFullContentWidth;

    public const int PER_PAGE = 20;

    public const int HEATMAP_DAYS = 7;

    public const int HEATMAP_HOURS = 24;

    public const int HEATMAP_STEP_SECONDS = 3600;

    public const int ACTION_LIMIT = 5;

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Actor activity';
    protected static ?string $title = 'Actor activity';

    protected string $view = 'eagle-eye-filament::pages.actor-profile';

    #[Locked]
    public string $actorType = '';

    #[Locked]
    public string $actorId = '';

    public int $page = 1;

    #[Locked]
    public string $activityCursorSignature = '';

    /** @var array<int, ?string> */
    #[Locked]
    public array $activityPageCursors = [1 => null];

    public bool $insightsLoaded = false;

    private ?Actor $resolvedActor = null;

    private bool $actorResolved = false;

    /** @var array{buckets: list<DateTimeImmutable>, series: array<string, list<int>>}|null */
    private ?array $series = null;

    private bool $seriesFailed = false;

    private ?int $errorCount = null;

    private bool $errorCountResolved = false;

    private ?string $lastSeen = null;

    private bool $lastSeenResolved = false;

    /** @var list<array{action: string, count: int, percent: float}>|null */
    private ?array $topActions = null;

    private bool $topActionsFailed = false;

    private ?ActivityPage $pageResult = null;

    public function mount(): void
    {
        abort_unless(app(LogAccessPolicyInterface::class)->canView(auth()->user()), 403);

        $actorType = request()->query('actor_type');
        $actorId = request()->query('actor_id');

        abort_unless(
            is_string($actorType) && '' !== $actorType && mb_strlen($actorType) <= 256 && 0 === preg_match('/[\x00-\x1F\x7F]/', $actorType)
            && is_string($actorId) && '' !== $actorId && mb_strlen($actorId) <= 256 && 0 === preg_match('/[\x00-\x1F\x7F]/', $actorId),
            404,
        );

        $this->actorType = $actorType;
        $this->actorId = $actorId;
    }

    public function render(): View
    {
        $this->authorizeAccess();

        return parent::render();
    }

    public function getTitle(): string
    {
        $this->authorizeAccess();

        return sprintf('Activity by %s %s', $this->actorType, $this->actorId);
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

    public function loadInsights(): void
    {
        $this->authorizeAccess();
        $this->insightsLoaded = true;
    }

    public function getActorLabel(): string
    {
        $this->authorizeAccess();
        $actor = $this->resolvedActor();

        if (null !== $actor->label && '' !== $actor->label) {
            return $actor->label;
        }

        return $this->actorType.':'.$this->actorId;
    }

    public function getActorInitials(): string
    {
        $this->authorizeAccess();

        return app(EntryFormatter::class)->initials($this->getActorLabel());
    }

    public function getRoleLabel(): string
    {
        $this->authorizeAccess();

        return $this->actorType;
    }

    public function getLastSeen(): ?string
    {
        $this->authorizeAccess();

        if ($this->lastSeenResolved) {
            return $this->lastSeen;
        }

        $this->lastSeenResolved = true;

        try {
            $result = $this->repository()->search(
                (new ActivityQuery())->forActor($this->actorType, $this->actorId)->limit(1),
            );
        } catch (Throwable) {
            return $this->lastSeen = null;
        }

        $entry = $result->entries[0] ?? null;

        return $this->lastSeen = $entry instanceof ActivityEntry
            ? app(EntryFormatter::class)->relativeTime($entry->loggedAt)
            : null;
    }

    public function seriesUnavailable(): bool
    {
        $this->authorizeAccess();

        return null === $this->series();
    }

    /** @return array{events: int, writes: int, errors: int, errorsAvailable: bool, exact: bool} */
    public function getStats(): array
    {
        $this->authorizeAccess();
        $series = $this->series();
        $events = 0;
        $writes = 0;

        if (is_array($series)) {
            foreach ($series['series'] as $group => $values) {
                $total = array_sum($values);
                $events += $total;

                if (ActivityChannel::Model->value === $group) {
                    $writes += $total;
                }
            }
        }

        $errors = $this->errorCount();

        return [
            'events'          => $events,
            'writes'          => $writes,
            'errors'          => $errors ?? 0,
            'errorsAvailable' => null !== $errors,
            'exact'           => Capabilities::supports(Capability::ExactCount),
        ];
    }

    /** @return array{rows: list<array{label: string, cells: list<array{level: int, count: int, label: string}>}>, total: int} */
    public function getHeatmap(): array
    {
        $this->authorizeAccess();
        $series = $this->series();

        if (! is_array($series)) {
            return ['rows' => [], 'total' => 0];
        }

        $totals = array_fill(0, count($series['buckets']), 0);

        foreach ($series['series'] as $values) {
            foreach ($values as $index => $value) {
                if (array_key_exists($index, $totals)) {
                    $totals[$index] += $value;
                }
            }
        }

        $levels = $this->heatLevels($totals);
        $rows = [];

        for ($day = 0; $day < self::HEATMAP_DAYS; $day++) {
            $cells = [];

            for ($hour = 0; $hour < self::HEATMAP_HOURS; $hour++) {
                $index = $day * self::HEATMAP_HOURS + $hour;
                $bucket = $series['buckets'][$index] ?? null;
                $count = $totals[$index] ?? 0;
                $cells[] = [
                    'level' => $levels[$index] ?? 0,
                    'count' => $count,
                    'label' => null !== $bucket
                        ? $bucket->format('Y-m-d H:00').' · '.$count.' '.$this->eventNoun($count)
                        : $count.' '.$this->eventNoun($count),
                ];
            }

            $firstBucket = $series['buckets'][$day * self::HEATMAP_HOURS] ?? null;
            $rows[] = [
                'label' => $firstBucket?->format('D d') ?? '',
                'cells' => $cells,
            ];
        }

        return ['rows' => $rows, 'total' => array_sum($totals)];
    }

    /** @return list<array{action: string, count: int, percent: float}> */
    public function getTopActions(): array
    {
        $this->authorizeAccess();

        if (null !== $this->topActions || $this->topActionsFailed) {
            return $this->topActions ?? [];
        }

        if (! Capabilities::supports(Capability::Aggregation)) {
            $this->topActionsFailed = true;

            return [];
        }

        [$from, $to] = $this->heatmapWindow();

        try {
            $groups = $this->repository()->aggregateTop(
                (new ActivityQuery())->forActor($this->actorType, $this->actorId)->between($from, $to),
                'action',
                self::ACTION_LIMIT,
            );
        } catch (Throwable) {
            $this->topActionsFailed = true;

            return [];
        }

        $maximum = [] === $groups ? 0 : max($groups);
        $rows = [];

        foreach ($groups as $action => $count) {
            $rows[] = [
                'action'  => (string) $action,
                'count'   => $count,
                'percent' => $maximum > 0 ? min(100.0, $count / $maximum * 100.0) : 0.0,
            ];
        }

        return $this->topActions = $rows;
    }

    public function topActionsUnavailable(): bool
    {
        $this->authorizeAccess();

        return $this->topActionsFailed;
    }

    public function getActivityPage(): ActivityPage
    {
        $this->authorizeAccess();

        if ($this->pageResult instanceof ActivityPage) {
            return $this->pageResult;
        }

        $viewer = auth()->user();
        $viewerKey = $viewer instanceof Authenticatable ? [$viewer::class, $viewer->getAuthIdentifier()] : [null, null];
        $signature = hash('sha256', 'actor|'.$this->actorType.'|'.$this->actorId.'|'.serialize($viewerKey));
        $history = CursorHistory::forSignature($signature, $this->activityCursorSignature, $this->activityPageCursors);
        $query = (new ActivityQuery())->forActor($this->actorType, $this->actorId)->orderByTime(SortOrder::Descending);

        $page = app(ActivityPageLoader::class)->load($query, $history, $this->page, self::PER_PAGE);
        $this->activityCursorSignature = $history->signature();
        $this->activityPageCursors = $history->cursors();

        return $this->pageResult = $page;
    }

    /** @return list<ActivityEntry> */
    public function getRecentEntries(): array
    {
        $this->authorizeAccess();

        return $this->getActivityPage()->entries;
    }

    public function nextPage(): void
    {
        $this->authorizeAccess();
        $this->page++;
        $this->pageResult = null;
    }

    public function previousPage(): void
    {
        $this->authorizeAccess();
        $this->page = max(1, $this->page - 1);
        $this->pageResult = null;
    }

    public function getExplorerUrl(): string
    {
        $this->authorizeAccess();

        return ActivityNav::explorerUrl(['actorType' => $this->actorType, 'actorId' => $this->actorId], '7d');
    }

    private function resolvedActor(): Actor
    {
        if ($this->actorResolved && $this->resolvedActor instanceof Actor) {
            return $this->resolvedActor;
        }

        $this->actorResolved = true;
        $actor = new Actor($this->actorType, $this->actorId);

        try {
            $resolved = app(ActivityIdentityResolverInterface::class)->resolveActors([$this->actorKey() => $actor]);
            $this->resolvedActor = $resolved[$this->actorKey()] ?? $actor;
        } catch (Throwable) {
            $this->resolvedActor = $actor;
        }

        return $this->resolvedActor;
    }

    private function actorKey(): string
    {
        try {
            return json_encode([$this->actorType, $this->actorId], JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->actorType.':'.$this->actorId;
        }
    }

    /** @return array{buckets: list<DateTimeImmutable>, series: array<string, list<int>>}|null */
    private function series(): ?array
    {
        if ($this->seriesFailed) {
            return null;
        }

        if (is_array($this->series)) {
            return $this->series;
        }

        if (! Capabilities::supports(Capability::SeriesAggregation)) {
            $this->seriesFailed = true;

            return null;
        }

        [$from, $to] = $this->heatmapWindow();

        try {
            return $this->series = $this->repository()->aggregateSeries(
                (new ActivityQuery())->forActor($this->actorType, $this->actorId)->between($from, $to),
                'channel',
                self::HEATMAP_STEP_SECONDS,
            );
        } catch (Throwable) {
            $this->seriesFailed = true;

            return null;
        }
    }

    private function errorCount(): ?int
    {
        if ($this->errorCountResolved) {
            return $this->errorCount;
        }

        $this->errorCountResolved = true;
        [$from, $to] = $this->heatmapWindow();

        try {
            return $this->errorCount = $this->repository()->count(
                (new ActivityQuery())
                    ->forActor($this->actorType, $this->actorId)
                    ->between($from, $to)
                    ->severities(
                        ActivitySeverity::Error,
                        ActivitySeverity::Critical,
                        ActivitySeverity::Alert,
                        ActivitySeverity::Emergency,
                    ),
            );
        } catch (Throwable) {
            return $this->errorCount = null;
        }
    }

    /** @return array{0: DateTimeImmutable, 1: DateTimeImmutable} */
    private function heatmapWindow(): array
    {
        $timezone = new DateTimeZone('UTC');
        $tomorrow = (new DateTimeImmutable('now', $timezone))->setTime(0, 0)->modify('+1 day');

        return [$tomorrow->modify('-'.self::HEATMAP_DAYS.' days'), $tomorrow];
    }

    /**
     * @param  list<int>  $values
     * @return list<int>
     */
    private function heatLevels(array $values): array
    {
        $positive = array_values(array_filter($values, static fn (int $value): bool => $value > 0));

        if ([] === $positive) {
            return array_fill(0, count($values), 0);
        }

        sort($positive);
        $count = count($positive);
        $thresholds = [];

        foreach ([0.25, 0.5, 0.75] as $quantile) {
            $thresholds[] = $positive[max(0, (int) floor($quantile * ($count - 1)))];
        }

        $levels = [];

        foreach ($values as $value) {
            if ($value <= 0) {
                $levels[] = 0;

                continue;
            }

            $level = 1;

            foreach ($thresholds as $threshold) {
                if ($value > $threshold) {
                    $level++;
                }
            }

            $levels[] = min(4, $level);
        }

        return $levels;
    }

    private function eventNoun(int $count): string
    {
        return 1 === $count ? 'event' : 'events';
    }

    private function repository(): ActivityRepositoryInterface
    {
        return app(ActivityRepositoryInterface::class);
    }
}
