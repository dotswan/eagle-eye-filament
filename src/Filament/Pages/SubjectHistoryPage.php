<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Filament\Pages;

use DateTimeImmutable;
use DateTimeZone;
use Dotswan\EagleEye\Contracts\Repository\ActivityRepositoryInterface;
use Dotswan\EagleEye\Contracts\Security\LogAccessPolicyInterface;
use Dotswan\EagleEye\Data\ActivityEntry;
use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Enums\Capability;
use Dotswan\EagleEye\Enums\SortOrder;
use Dotswan\EagleEye\Http\Resources\ActivityEntryResource;
use Dotswan\EagleEyeFilament\Filament\Concerns\AuthorizesActivityAccess;
use Dotswan\EagleEyeFilament\Filament\Concerns\HasFullContentWidth;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource;
use Dotswan\EagleEyeFilament\Support\Capabilities;
use Dotswan\EagleEyeFilament\Support\Pagination\ActivityPage;
use Dotswan\EagleEyeFilament\Support\Pagination\ActivityPageLoader;
use Dotswan\EagleEyeFilament\Support\Pagination\CursorHistory;
use Dotswan\EagleEyeFilament\Support\Presentation\ActivityNav;
use Dotswan\EagleEyeFilament\Support\Presentation\ChangeRowsBuilder;
use Dotswan\EagleEyeFilament\Support\Presentation\EntryFormatter;
use Filament\Pages\Page;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use LengthException;
use Livewire\Attributes\Locked;
use Throwable;

final class SubjectHistoryPage extends Page
{
    use AuthorizesActivityAccess;
    use HasFullContentWidth;

    public const int PER_PAGE = 20;

    public const int MAX_EDITORS = 1000;

    protected static ?string $slug = 'activity-subject';

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Record history';
    protected static ?string $title = 'Record history';

    protected string $view = 'eagle-eye-filament::pages.subject-history';

    #[Locked]
    public string $subjectType = '';

    #[Locked]
    public string $subjectId = '';

    public int $page = 1;

    #[Locked]
    public string $activityCursorSignature = '';

    /** @var array<int, ?string> */
    #[Locked]
    public array $activityPageCursors = [1 => null];

    public bool $statsLoaded = false;

    private ?ActivityPage $pageResult = null;

    /**
     * @var array{events: ?int, eventsApprox: bool, editors: string, editorsExact: bool, firstSeen: ?string, firstSeenApprox: bool}|null
     */
    private ?array $stats = null;

    public function mount(): void
    {
        abort_unless(app(LogAccessPolicyInterface::class)->canView(auth()->user()), 403);

        $subjectType = request()->query('subject_type');
        $subjectId = request()->query('subject_id');

        abort_unless(
            is_string($subjectType) && '' !== $subjectType && mb_strlen($subjectType) <= 256 && 0 === preg_match('/[\x00-\x1F\x7F]/', $subjectType)
            && is_string($subjectId) && '' !== $subjectId && mb_strlen($subjectId) <= 256 && 0 === preg_match('/[\x00-\x1F\x7F]/', $subjectId),
            404,
        );

        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
    }

    public function render(): View
    {
        $this->authorizeAccess();

        return parent::render();
    }

    public function getTitle(): string
    {
        $this->authorizeAccess();

        return sprintf('History of %s %s', $this->subjectType, $this->subjectId);
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

    public function loadStats(): void
    {
        $this->authorizeAccess();
        $this->statsLoaded = true;
    }

    public function getActivityPage(): ActivityPage
    {
        $this->authorizeAccess();

        if ($this->pageResult instanceof ActivityPage) {
            return $this->pageResult;
        }

        $viewer = auth()->user();
        $viewerKey = $viewer instanceof Authenticatable ? [$viewer::class, $viewer->getAuthIdentifier()] : [null, null];
        $signature = hash('sha256', 'subject|'.$this->subjectType.'|'.$this->subjectId.'|'.serialize($viewerKey));
        $history = CursorHistory::forSignature($signature, $this->activityCursorSignature, $this->activityPageCursors);
        $query = (new ActivityQuery())->forSubject($this->subjectType, $this->subjectId)->orderByTime(SortOrder::Descending);

        $page = app(ActivityPageLoader::class)->load($query, $history, $this->page, self::PER_PAGE);
        $this->activityCursorSignature = $history->signature();
        $this->activityPageCursors = $history->cursors();

        return $this->pageResult = $page;
    }

    /**
     * @return list<array{
     *     id: string,
     *     url: string,
     *     time: string,
     *     date: ?string,
     *     relative: string,
     *     actor: string,
     *     action: string,
     *     tone: string,
     *     title: string,
     *     changes: list<array{label: string, before: string, after: string, beforeFull: ?string, afterFull: ?string}>
     * }>
     */
    public function getVersions(): array
    {
        $this->authorizeAccess();
        $formatter = app(EntryFormatter::class);
        $today = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
        $versions = [];

        foreach ($this->getActivityPage()->entries as $entry) {
            $loggedAt = $entry->loggedAt;
            $date = $loggedAt->format('Y-m-d');

            $versions[] = [
                'id'       => $entry->id,
                'url'      => ActivityLogResource::getUrl('view', ['record' => $entry->id]),
                'time'     => $loggedAt->format('H:i:s.v'),
                'date'     => $date === $today ? null : $date,
                'relative' => $formatter->relativeTime($loggedAt),
                'actor'    => $formatter->actorLabel($entry->actor),
                'action'   => $entry->action,
                'tone'     => $formatter->channelTone($entry->channel),
                'title'    => $entry->title,
                'changes'  => $this->changeRows($entry),
            ];
        }

        return $versions;
    }

    /**
     * @return array{events: ?int, eventsApprox: bool, editors: string, editorsExact: bool, firstSeen: ?string, firstSeenApprox: bool}
     */
    public function getStats(): array
    {
        $this->authorizeAccess();

        if (is_array($this->stats)) {
            return $this->stats;
        }

        $events = null;

        try {
            $events = $this->repository()->count((new ActivityQuery())->forSubject($this->subjectType, $this->subjectId));
        } catch (Throwable) {
            $events = null;
        }

        $editors = '—';
        $editorsExact = false;

        if (Capabilities::supports(Capability::Aggregation)) {
            try {
                $editors = (string) count($this->repository()->aggregate(
                    (new ActivityQuery())->forSubject($this->subjectType, $this->subjectId),
                    'actor',
                ));
                $editorsExact = true;
            } catch (LengthException) {
                $editors = self::MAX_EDITORS.'+';
                $editorsExact = false;
            } catch (Throwable) {
                $editors = '—';
                $editorsExact = false;
            }
        }

        $firstSeen = null;
        $firstSeenApprox = false;

        try {
            $result = $this->repository()->search(
                (new ActivityQuery())->forSubject($this->subjectType, $this->subjectId)->orderByTime(SortOrder::Ascending)->limit(1),
            );
            $entry = $result->entries[0] ?? null;

            if ($entry instanceof ActivityEntry) {
                $firstSeen = $entry->loggedAt->format('Y-m-d H:i');
                $firstSeenApprox = $this->hitsReadWindowFloor($entry->loggedAt);
            }
        } catch (Throwable) {
            $firstSeen = null;
            $firstSeenApprox = false;
        }

        return $this->stats = [
            'events'          => $events,
            'eventsApprox'    => ! Capabilities::supports(Capability::ExactCount),
            'editors'         => $editors,
            'editorsExact'    => $editorsExact,
            'firstSeen'       => $firstSeen,
            'firstSeenApprox' => $firstSeenApprox,
        ];
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

    /** @return list<array{label: string, kind: string, before: string, after: string, beforeFull: ?string, afterFull: ?string, formattingOnly: bool, truncated: bool, paths: list<array{path: string, before: string, after: string}>}> */
    private function changeRows(ActivityEntry $entry): array
    {
        $presented = (new ActivityEntryResource($entry))->toArray(request());
        $changes = $presented['changes'] ?? [];

        if (! is_array($changes)) {
            return [];
        }

        return app(ChangeRowsBuilder::class)->build($changes);
    }

    private function hitsReadWindowFloor(DateTimeImmutable $at): bool
    {
        if (Capabilities::supports(Capability::UnboundedTimeRange)) {
            return false;
        }

        $days = (int) config('eagle-eye.drivers.loki.read.default_range_days', 30);
        $floor = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('-'.$days.' days');

        return $at->getTimestamp() <= $floor->getTimestamp() + 3600;
    }

    private function repository(): ActivityRepositoryInterface
    {
        return app(ActivityRepositoryInterface::class);
    }
}
