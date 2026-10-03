<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Filament\Pages;

use DateTimeImmutable;
use Dotswan\EagleEye\Contracts\Repository\ActivityRepositoryInterface;
use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Enums\SortOrder;
use Dotswan\EagleEyeFilament\Filament\Concerns\AuthorizesActivityAccess;
use Dotswan\EagleEyeFilament\Filament\Concerns\HasFullContentWidth;
use Dotswan\EagleEyeFilament\Support\Pagination\ActivityPage;
use Dotswan\EagleEyeFilament\Support\Pagination\ActivityPageLoader;
use Dotswan\EagleEyeFilament\Support\Pagination\CursorHistory;
use Dotswan\EagleEyeFilament\Support\Presentation\ActivityNav;
use Dotswan\EagleEyeFilament\Support\Presentation\EntryFormatter;
use Filament\Pages\Page;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use LengthException;
use Livewire\Attributes\Locked;

final class RecentTracesPage extends Page
{
    use AuthorizesActivityAccess;
    use HasFullContentWidth;

    public const int PER_PAGE = 20;

    public const int EVENT_WINDOW_LEAD_SECONDS = 1;

    public const int EVENT_WINDOW_TAIL_SECONDS = 600;

    protected string $view = 'eagle-eye-filament::pages.recent-traces';

    protected static ?string $slug = 'activity-traces';

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Recent traces';
    protected static ?string $title = 'Recent traces';

    public int $page = 1;

    #[Locked]
    public string $activityCursorSignature = '';

    /** @var array<int, ?string> */
    #[Locked]
    public array $activityPageCursors = [1 => null];

    private ?ActivityPage $pageResult = null;

    /** @var array<string, int>|null */
    private ?array $eventCounts = null;

    private bool $eventCountsFailed = false;

    public function render(): View
    {
        $this->authorizeAccess();

        return parent::render();
    }

    public function getTitle(): string
    {
        $this->authorizeAccess();

        return 'Recent traces';
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

    public function getActivityPage(): ActivityPage
    {
        $this->authorizeAccess();

        if ($this->pageResult instanceof ActivityPage) {
            return $this->pageResult;
        }

        $viewer = auth()->user();
        $viewerKey = $viewer instanceof Authenticatable ? [$viewer::class, $viewer->getAuthIdentifier()] : [null, null];
        $signature = hash('sha256', 'recent-traces|http|'.serialize($viewerKey));
        $history = CursorHistory::forSignature($signature, $this->activityCursorSignature, $this->activityPageCursors);
        $query = (new ActivityQuery())->channels(ActivityChannel::Http)->orderByTime(SortOrder::Descending);

        $page = app(ActivityPageLoader::class)->load($query, $history, $this->page, self::PER_PAGE);
        $this->activityCursorSignature = $history->signature();
        $this->activityPageCursors = $history->cursors();

        return $this->pageResult = $page;
    }

    /**
     * @return list<array{
     *     id: string,
     *     traceId: string,
     *     url: string,
     *     title: string,
     *     route: ?string,
     *     method: ?string,
     *     statusCode: ?int,
     *     statusTone: string,
     *     durationLabel: string,
     *     durationTone: string,
     *     actor: string,
     *     time: string,
     *     relativeTime: string,
     *     events: ?int
     * }>
     */
    public function getRows(): array
    {
        $this->authorizeAccess();
        $page = $this->getActivityPage();

        if ([] === $page->entries) {
            return [];
        }

        $counts = $this->eventCounts();
        $formatter = app(EntryFormatter::class);
        $rows = [];

        foreach ($page->entries as $entry) {
            $traceId = $entry->trace->traceId;
            $durationMs = $entry->request?->durationMs;

            $rows[] = [
                'id'            => $entry->id,
                'traceId'       => $traceId,
                'url'           => TraceTimelinePage::getUrl(['trace' => $traceId]),
                'title'         => $entry->title,
                'route'         => $entry->request?->route,
                'method'        => $entry->request?->method,
                'statusCode'    => $entry->request?->statusCode,
                'statusTone'    => $this->statusTone($entry->request?->statusCode),
                'durationLabel' => $formatter->durationLabel($durationMs),
                'durationTone'  => null !== $durationMs && $durationMs >= 1000 ? 'amber' : 'neutral',
                'actor'         => $formatter->actorLabel($entry->actor),
                'time'          => $entry->loggedAt->format('Y-m-d H:i:s'),
                'relativeTime'  => $formatter->relativeTime($entry->loggedAt),
                'events'        => null !== $counts ? ($counts[$traceId] ?? null) : null,
            ];
        }

        return $rows;
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

    /** @return array<string, int>|null */
    private function eventCounts(): ?array
    {
        if (null !== $this->eventCounts) {
            return $this->eventCounts;
        }

        if ($this->eventCountsFailed) {
            return null;
        }

        $page = $this->getActivityPage();

        if ([] === $page->entries) {
            return $this->eventCounts = [];
        }

        $min = $page->entries[0]->loggedAt;
        $max = $page->entries[0]->loggedAt;

        foreach ($page->entries as $entry) {
            if ($entry->loggedAt < $min) {
                $min = $entry->loggedAt;
            }

            if ($entry->loggedAt > $max) {
                $max = $entry->loggedAt;
            }
        }

        try {
            return $this->eventCounts = $this->repository()->aggregate(
                (new ActivityQuery())->between(
                    $this->shift($min, -self::EVENT_WINDOW_LEAD_SECONDS),
                    $this->shift($max, self::EVENT_WINDOW_TAIL_SECONDS),
                ),
                'trace_id',
            );
        } catch (LengthException|InvalidArgumentException) {
            $this->eventCountsFailed = true;

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

    private function repository(): ActivityRepositoryInterface
    {
        return app(ActivityRepositoryInterface::class);
    }
}
