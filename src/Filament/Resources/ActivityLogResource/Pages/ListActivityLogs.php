<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource\Pages;

use DateTimeImmutable;
use DateTimeZone;
use Dotswan\EagleEye\Data\ActivityEntry;
use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Enums\ActivitySeverity;
use Dotswan\EagleEye\Enums\Capability;
use Dotswan\EagleEye\Enums\SortOrder;
use Dotswan\EagleEyeFilament\Filament\Concerns\AuthorizesActivityAccess;
use Dotswan\EagleEyeFilament\Filament\Concerns\HasFullContentWidth;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource;
use Dotswan\EagleEyeFilament\Support\Capabilities;
use Dotswan\EagleEyeFilament\Support\Explorer\ActivityFilterState;
use Dotswan\EagleEyeFilament\Support\Explorer\FacetCounter;
use Dotswan\EagleEyeFilament\Support\Explorer\InvalidActivityFilter;
use Dotswan\EagleEyeFilament\Support\Explorer\SearchTokenParser;
use Dotswan\EagleEyeFilament\Support\Pagination\ActivityPage;
use Dotswan\EagleEyeFilament\Support\Pagination\ActivityPageLoader;
use Dotswan\EagleEyeFilament\Support\Pagination\CursorHistory;
use Dotswan\EagleEyeFilament\Support\Presentation\ActivityNav;
use Dotswan\EagleEyeFilament\Support\Presentation\EntryFormatter;
use Dotswan\EagleEyeFilament\Support\Stats\ActivityRange;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

final class ListActivityLogs extends Page
{
    use AuthorizesActivityAccess;
    use HasFullContentWidth;

    public const int PER_PAGE = 25;

    /** @var list<int> */
    public const array PER_PAGE_OPTIONS = [25, 50, 100];

    /** @var list<string> */
    public const array RANGES = ['1h', '24h', '7d', '30d', 'custom'];

    public const int MAX_OPEN_GROUPS = 50;

    protected static string $resource = ActivityLogResource::class;

    protected string $view = 'eagle-eye-filament::pages.explorer';

    /** @var list<string> */
    #[Url(as: 'channel')]
    public array $channels = [];

    /** @var list<string> */
    #[Url(as: 'severity')]
    public array $severities = [];

    /** @var list<string> */
    #[Url(as: 'actions')]
    public array $actions = [];

    #[Url]
    public string $q = '';

    #[Url]
    public ?string $actorType = null;

    #[Url]
    public ?string $actorId = null;

    #[Url]
    public ?string $subjectType = null;

    #[Url]
    public ?string $subjectId = null;

    #[Url]
    public ?string $trace = null;

    #[Url(as: 'per_page', except: 25)]
    public int $perPage = 25;

    #[Url(except: '24h')]
    public string $range = '24h';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $until = null;

    #[Url]
    public string $variant = 'stream';

    public string $searchInput = '';

    public int $page = 1;

    public bool $customRangeOpen = false;

    public ?string $draftFrom = null;

    public ?string $draftUntil = null;

    #[Locked]
    public ?string $windowEndsAt = null;

    #[Locked]
    public string $activityCursorSignature = '';

    /** @var array<int, ?string> */
    #[Locked]
    public array $activityPageCursors = [1 => null];

    public bool $facetsLoaded = false;

    private ?ActivityFilterState $filterState = null;

    private bool $filterFailed = false;

    private ?string $filterError = null;

    private ?ActivityPage $pageResult = null;

    /** @var list<string> */
    private array $searchErrors = [];

    /** @var list<string> */
    private array $searchWarnings = [];

    public function mount(): void
    {
        if (! is_string($this->defaultAction) || '' === $this->defaultAction) {
            $this->defaultAction = null;
        }

        if (! is_array($this->defaultActionArguments)) {
            $this->defaultActionArguments = null;
        }

        if (! is_array($this->defaultActionContext)) {
            $this->defaultActionContext = null;
        }

        if (! in_array($this->range, self::RANGES, true)) {
            abort(422, 'The selected activity range is invalid.');
        }

        if ((null !== $this->from || null !== $this->until) && 'custom' !== $this->range) {
            $this->range = 'custom';
        }

        if ('custom' === $this->range) {
            $this->customRangeOpen = true;
            $this->draftFrom = $this->from;
            $this->draftUntil = $this->until;
        }
    }

    public function render(): View
    {
        $this->authorizeAccess();

        return parent::render();
    }

    public function getTitle(): string
    {
        $this->authorizeAccess();

        return 'Activity explorer';
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

    public function getActivityPage(): ?ActivityPage
    {
        $this->authorizeAccess();
        $this->assertPerPageValid();

        if (null !== $this->getFilterError()) {
            return null;
        }

        if ($this->pageResult instanceof ActivityPage) {
            return $this->pageResult;
        }

        if ([] !== $this->searchErrors) {
            return null;
        }

        $filter = $this->filterState();

        if (! $filter instanceof ActivityFilterState) {
            return null;
        }

        $viewer = auth()->user();
        $viewerKey = $viewer instanceof Authenticatable ? [$viewer::class, $viewer->getAuthIdentifier()] : [null, null];
        $signature = hash('sha256', $filter->signature().'|'.$this->windowKey().'|'.$this->perPage.'|'.serialize($viewerKey));
        $history = CursorHistory::forSignature($signature, $this->activityCursorSignature, $this->activityPageCursors);
        $query = $this->applyRange($filter->applyTo(new ActivityQuery()))->orderByTime(SortOrder::Descending);

        if (null !== $query->search && ! Capabilities::supports(Capability::FullTextSearch)) {
            $query->search = null;
        }

        $page = app(ActivityPageLoader::class)->load($query, $history, $this->page, $this->perPage);
        $this->activityCursorSignature = $history->signature();
        $this->activityPageCursors = $history->cursors();

        return $this->pageResult = $page;
    }

    public function getFilterError(): ?string
    {
        $this->authorizeAccess();
        $this->filterState();

        return $this->filterError;
    }

    /** @return list<string> */
    public function getSearchErrors(): array
    {
        $this->authorizeAccess();

        return $this->searchErrors;
    }

    /** @return list<string> */
    public function getSearchWarnings(): array
    {
        $this->authorizeAccess();

        return $this->searchWarnings;
    }

    public function getSearchCapabilityMessage(): ?string
    {
        $this->authorizeAccess();

        if (Capabilities::supports(Capability::FullTextSearch)) {
            return null;
        }

        return 'Free-text search is not supported by the active log backend.';
    }

    public function isFullTextSearchSupported(): bool
    {
        return Capabilities::supports(Capability::FullTextSearch);
    }

    public function getResultLabel(): string
    {
        $this->authorizeAccess();
        $label = $this->getWindowLabel();
        $page = $this->getActivityPage();

        if (! $page instanceof ActivityPage) {
            return $label;
        }

        $count = count($page->entries);

        if (0 === $count) {
            return $label.' · No events';
        }

        $start = ($page->page - 1) * $page->perPage + 1;
        $end = $start + $count - 1;
        $total = $this->resultCount();

        return $label.' · Showing '.$start.'–'.$end.(null !== $total ? ' of '.$total : '');
    }

    public function getCountWindowLabel(): string
    {
        $this->authorizeAccess();

        return 'Counts · '.$this->getWindowLabel();
    }

    public function getWindowLabel(): string
    {
        $this->authorizeAccess();

        return match ($this->range) {
            '1h'    => 'Last hour',
            '24h'   => 'Last 24 hours',
            '7d'    => 'Last 7 days',
            '30d'   => 'Last 30 days',
            default => $this->customWindowLabel(),
        };
    }

    /** @return list<ActivityEntry> */
    public function getEntries(): array
    {
        $this->authorizeAccess();
        $page = $this->getActivityPage();

        return $page instanceof ActivityPage ? $page->entries : [];
    }

    public function getFirstEntryId(): ?string
    {
        $this->authorizeAccess();

        return $this->getEntries()[0]->id ?? null;
    }

    public function getVariant(): string
    {
        return in_array($this->variant, ['stream', 'trace'], true) ? $this->variant : 'stream';
    }

    /** @return list<array{label: string, active: bool, wire: string, tone: string, title: ?string}> */
    public function getVariantItems(): array
    {
        $this->authorizeAccess();
        $variant = $this->getVariant();

        return [
            ['label' => 'Stream', 'active' => 'stream' === $variant, 'wire' => "setVariant('stream')", 'tone' => 'blue', 'title' => 'Newest first'],
            ['label' => 'By trace', 'active' => 'trace' === $variant, 'wire' => "setVariant('trace')", 'tone' => 'violet', 'title' => 'Grouped by trace'],
        ];
    }

    public function setVariant(string $variant): void
    {
        $this->authorizeAccess();

        if (in_array($variant, ['stream', 'trace'], true)) {
            $this->variant = $variant;
        }
    }

    /** @return list<array{label: string, active: bool, muted: bool, wire: string, tone: string, title: ?string}> */
    public function getRangeOptions(): array
    {
        $this->authorizeAccess();

        $items = [];

        foreach (ActivityRange::cases() as $range) {
            $isCurrent = $this->range === $range->value;
            $items[] = [
                'label'  => $range->value,
                'active' => $isCurrent,
                'muted'  => $isCurrent && $this->customRangeOpen,
                'wire'   => 'setRange('.json_encode($range->value, JSON_THROW_ON_ERROR).')',
                'tone'   => 'blue',
                'title'  => 'Last '.$range->value,
            ];
        }

        $items[] = [
            'label'  => 'Custom',
            'active' => 'custom' === $this->range || $this->customRangeOpen,
            'muted'  => false,
            'wire'   => 'openCustomRange',
            'tone'   => 'amber',
            'title'  => 'Custom date range',
        ];

        return $items;
    }

    public function setRange(string $value): void
    {
        $this->authorizeAccess();
        $range = ActivityRange::tryFrom($value);

        if (! $range instanceof ActivityRange) {
            abort(422, 'The selected activity range is invalid.');
        }

        $this->range = $range->value;
        $this->from = null;
        $this->until = null;
        $this->draftFrom = null;
        $this->draftUntil = null;
        $this->customRangeOpen = false;
        $this->filterState = null;
        $this->filterError = null;
        $this->resetPaging();
    }

    public function setPerPage(int $value): void
    {
        $this->authorizeAccess();

        if (! in_array($value, self::PER_PAGE_OPTIONS, true)) {
            abort(422, 'The selected page size is invalid.');
        }

        $this->perPage = $value;
        $this->resetPaging();
    }

    public function firstPage(): void
    {
        $this->authorizeAccess();
        $this->page = 1;
        $this->windowEndsAt = null;
        $this->pageResult = null;
    }

    public function openCustomRange(): void
    {
        $this->authorizeAccess();
        $this->customRangeOpen = true;

        [$from, $until] = $this->window();
        $timezone = new DateTimeZone((string) config('app.timezone', 'UTC'));
        $this->draftFrom = $from->setTimezone($timezone)->format('Y-m-d');
        $this->draftUntil = $until->modify('-1 second')->setTimezone($timezone)->format('Y-m-d');
    }

    public function applyCustomRange(): void
    {
        $this->authorizeAccess();
        $this->filterError = null;

        if ((null === $this->draftFrom || '' === $this->draftFrom) && (null === $this->draftUntil || '' === $this->draftUntil)) {
            $this->filterError = 'Choose a start or end date for the custom range.';

            return;
        }

        try {
            ActivityFilterState::fromInput(['from' => $this->draftFrom, 'until' => $this->draftUntil]);
        } catch (InvalidActivityFilter $exception) {
            $this->filterError = $exception->getMessage();

            return;
        }

        $this->range = 'custom';
        $this->from = $this->draftFrom;
        $this->until = $this->draftUntil;
        $this->customRangeOpen = false;
        $this->filterState = null;
        $this->filterError = null;
        $this->resetPaging();
    }

    public function cancelCustomRange(): void
    {
        $this->authorizeAccess();
        $this->customRangeOpen = false;
        $this->draftFrom = null;
        $this->draftUntil = null;
    }

    public function submitSearch(): void
    {
        $this->authorizeAccess();
        $parsed = app(SearchTokenParser::class)->parse($this->searchInput);

        if ($parsed->hasErrors()) {
            $this->searchErrors = $parsed->errors;
            $this->searchWarnings = [];

            return;
        }

        if ('' !== $parsed->freeText && ! Capabilities::supports(Capability::FullTextSearch)) {
            $this->searchErrors = ['Free-text search is not supported by the active log backend.'];
            $this->searchWarnings = [];

            return;
        }

        $this->searchErrors = [];
        $this->searchWarnings = $parsed->warnings;
        $criteria = $parsed->criteria;
        $this->channels = $criteria['channels'];
        $this->severities = $criteria['severities'];
        $this->actions = $criteria['actions'];

        if (null !== $criteria['actorType']) {
            $this->actorType = $criteria['actorType'];
            $this->actorId = $criteria['actorId'];
        }

        if (null !== $criteria['subjectType']) {
            $this->subjectType = $criteria['subjectType'];
            $this->subjectId = $criteria['subjectId'];
        }

        if (null !== $criteria['trace']) {
            $this->trace = $criteria['trace'];
        }

        $this->q = $parsed->freeText;
        $this->resetPaging();
    }

    /** @return list<array{type: string, value: string, label: string}> */
    public function getFilterChips(): array
    {
        $this->authorizeAccess();
        $chips = [];

        foreach ($this->channels as $value) {
            $chips[] = ['type' => 'channel', 'value' => $value, 'label' => $value];
        }

        foreach ($this->severities as $value) {
            $chips[] = ['type' => 'severity', 'value' => $value, 'label' => $value];
        }

        foreach ($this->actions as $value) {
            $chips[] = ['type' => 'action', 'value' => $value, 'label' => $value];
        }

        if (null !== $this->actorType) {
            $chips[] = ['type' => 'actor', 'value' => $this->actorType, 'label' => 'actor:'.(null !== $this->actorId ? $this->actorType.':'.$this->actorId : $this->actorType)];
        }

        if (null !== $this->subjectType) {
            $chips[] = ['type' => 'subject', 'value' => $this->subjectType, 'label' => 'subject:'.(null !== $this->subjectId ? $this->subjectType.':'.$this->subjectId : $this->subjectType)];
        }

        if (null !== $this->trace) {
            $chips[] = ['type' => 'trace', 'value' => $this->trace, 'label' => 'trace:'.$this->trace];
        }

        if ('' !== $this->q) {
            $chips[] = ['type' => 'q', 'value' => $this->q, 'label' => $this->q];
        }

        return $chips;
    }

    public function hasActiveFilters(): bool
    {
        return [] !== $this->getFilterChips();
    }

    public function removeFilter(string $type, string $value): void
    {
        $this->authorizeAccess();

        switch ($type) {
            case 'channel':
                $this->channels = array_values(array_filter($this->channels, static fn (mixed $item): bool => $item !== $value));
                break;
            case 'severity':
                $this->severities = array_values(array_filter($this->severities, static fn (mixed $item): bool => $item !== $value));
                break;
            case 'action':
                $this->actions = array_values(array_filter($this->actions, static fn (mixed $item): bool => $item !== $value));
                break;
            case 'actor':
                $this->actorType = null;
                $this->actorId = null;
                break;
            case 'subject':
                $this->subjectType = null;
                $this->subjectId = null;
                break;
            case 'trace':
                $this->trace = null;
                break;
            case 'q':
                $this->q = '';
                $this->searchInput = '';
                break;
        }

        $this->resetPaging();
    }

    public function resetFilters(): void
    {
        $this->authorizeAccess();
        $this->channels = [];
        $this->severities = [];
        $this->actions = [];
        $this->q = '';
        $this->searchInput = '';
        $this->actorType = null;
        $this->actorId = null;
        $this->subjectType = null;
        $this->subjectId = null;
        $this->trace = null;
        $this->range = '24h';
        $this->from = null;
        $this->until = null;
        $this->draftFrom = null;
        $this->draftUntil = null;
        $this->customRangeOpen = false;
        $this->searchErrors = [];
        $this->searchWarnings = [];
        $this->filterState = null;
        $this->filterError = null;
        $this->resetPaging();
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

    public function loadFacets(): void
    {
        $this->authorizeAccess();
        $this->facetsLoaded = true;
    }

    /** @return list<array{value: string, label: string, count: ?int, active: bool, tone: string}> */
    public function getChannelFacets(): array
    {
        $this->authorizeAccess();
        $counts = $this->channelCounts();
        $rows = [];

        foreach (ActivityChannel::cases() as $channel) {
            $rows[] = [
                'value'  => $channel->value,
                'label'  => $channel->value,
                'count'  => null === $counts ? null : ($counts[$channel->value] ?? 0),
                'active' => in_array($channel->value, $this->channels, true),
                'tone'   => $this->formatter()->channelTone($channel),
            ];
        }

        return $rows;
    }

    /** @return list<array{value: string, label: string, count: ?int, active: bool, tone: string}> */
    public function getSeverityFacets(): array
    {
        $this->authorizeAccess();
        $counts = $this->severityCounts();
        $rows = [];

        foreach (ActivitySeverity::cases() as $severity) {
            $rows[] = [
                'value'  => $severity->value,
                'label'  => $severity->value,
                'count'  => null === $counts ? null : ($counts[$severity->value] ?? 0),
                'active' => in_array($severity->value, $this->severities, true),
                'tone'   => $this->formatter()->severityTone($severity),
            ];
        }

        return $rows;
    }

    /** @return list<array{value: string, label: string, count: ?int, active: bool, tone: string}> */
    public function getActionFacets(): array
    {
        $this->authorizeAccess();
        $counts = $this->actionCounts();
        $rows = [];

        if (null !== $counts) {
            foreach ($counts as $value => $count) {
                $rows[] = [
                    'value'  => $value,
                    'label'  => $value,
                    'count'  => $count,
                    'active' => in_array($value, $this->actions, true),
                    'tone'   => 'action',
                ];
            }
        }

        foreach ($this->actions as $value) {
            if (! in_array($value, array_column($rows, 'value'), true)) {
                $rows[] = ['value' => $value, 'label' => $value, 'count' => null, 'active' => true, 'tone' => 'action'];
            }
        }

        return $rows;
    }

    public function channelCountsUnavailable(): bool
    {
        $this->authorizeAccess();

        return null === $this->channelCounts() && $this->facetQuery() instanceof ActivityQuery;
    }

    public function severityCountsUnavailable(): bool
    {
        $this->authorizeAccess();

        return null === $this->severityCounts() && $this->facetQuery() instanceof ActivityQuery;
    }

    public function actionCountsUnavailable(): bool
    {
        $this->authorizeAccess();

        return null === $this->actionCounts() && $this->facetQuery() instanceof ActivityQuery;
    }

    /**
     * @return list<array{traceId: string, header: ActivityEntry, actor: string, statusTone: string, count: int, spanMs: int, partial: bool, entries: list<array{entry: ActivityEntry, offset: string, percent: float, tone: string}>}>
     */
    public function getTraceGroups(): array
    {
        $this->authorizeAccess();

        if ('trace' !== $this->getVariant()) {
            return [];
        }

        $page = $this->getActivityPage();

        if (! $page instanceof ActivityPage || [] === $page->entries) {
            return [];
        }

        $entries = $page->entries;
        $lastIndex = count($entries) - 1;
        $grouped = [];
        $ranges = [];

        foreach ($entries as $index => $entry) {
            $traceId = $entry->trace->traceId;
            $grouped[$traceId] ??= [];
            $grouped[$traceId][] = $entry;
            $ranges[$traceId]['min'] = min($ranges[$traceId]['min'] ?? $index, $index);
            $ranges[$traceId]['max'] = max($ranges[$traceId]['max'] ?? $index, $index);
        }

        $formatter = $this->formatter();
        $groups = [];

        foreach ($grouped as $traceId => $groupEntries) {
            $earliest = $this->earliestOf($groupEntries);
            $header = $this->groupHeader($groupEntries, $earliest);
            $sorted = $groupEntries;
            usort($sorted, static fn (ActivityEntry $left, ActivityEntry $right): int => $left->loggedAt <=> $right->loggedAt);
            $spanMs = 1;
            $rows = [];

            foreach ($sorted as $entry) {
                $spanMs = max($spanMs, $this->offsetMilliseconds($entry->loggedAt, $earliest));
            }

            foreach ($sorted as $entry) {
                $offsetMs = $this->offsetMilliseconds($entry->loggedAt, $earliest);
                $rows[] = [
                    'entry'   => $entry,
                    'offset'  => $formatter->offsetLabel($offsetMs * 1000),
                    'percent' => min(99.5, ($offsetMs / $spanMs) * 100.0),
                    'tone'    => $formatter->channelTone($entry->channel),
                ];
            }

            $range = $ranges[$traceId];
            $groups[] = [
                'traceId'    => $traceId,
                'header'     => $header,
                'actor'      => $formatter->actorLabel($header->actor),
                'statusTone' => $this->statusTone($header->request?->statusCode),
                'count'      => count($groupEntries),
                'spanMs'     => $spanMs,
                'partial'    => (0 === $range['min'] && $page->hasPrevious) || ($lastIndex === $range['max'] && $page->hasNext),
                'entries'    => $rows,
            ];
        }

        return $groups;
    }

    public function updatedChannels(): void
    {
        $this->resetPaging();
    }

    public function updatedSeverities(): void
    {
        $this->resetPaging();
    }

    public function updatedActions(): void
    {
        $this->resetPaging();
    }

    public function updatedActorType(): void
    {
        $this->resetPaging();
    }

    public function updatedActorId(): void
    {
        $this->resetPaging();
    }

    public function updatedSubjectType(): void
    {
        $this->resetPaging();
    }

    public function updatedSubjectId(): void
    {
        $this->resetPaging();
    }

    public function updatedTrace(): void
    {
        $this->resetPaging();
    }

    public function updatedQ(): void
    {
        $this->resetPaging();
    }

    public function entryChannelTone(ActivityEntry $entry): string
    {
        return $this->formatter()->channelTone($entry->channel);
    }

    public function entrySeverityTone(ActivityEntry $entry): string
    {
        return $this->formatter()->severityTone($entry->severity);
    }

    public function entryActorLabel(ActivityEntry $entry): string
    {
        return $this->formatter()->actorLabel($entry->actor);
    }

    public function entryRelativeTime(ActivityEntry $entry): string
    {
        return $this->formatter()->relativeTime($entry->loggedAt);
    }

    public function getTraceUrl(string $traceId): ?string
    {
        $this->authorizeAccess();

        return ActivityNav::traceUrl($traceId);
    }

    private function filterState(): ?ActivityFilterState
    {
        if ($this->filterState instanceof ActivityFilterState) {
            return $this->filterState;
        }

        if ($this->filterFailed) {
            return null;
        }

        if ('custom' === $this->range) {
            try {
                ActivityFilterState::fromInput(['from' => $this->from, 'until' => $this->until]);
            } catch (InvalidActivityFilter $exception) {
                $this->filterFailed = true;
                $this->filterError = $exception->getMessage();

                return null;
            }
        }

        try {
            return $this->filterState = ActivityFilterState::fromInput([
                'channels'    => $this->channels,
                'severities'  => $this->severities,
                'actions'     => $this->actions,
                'actorType'   => $this->actorType,
                'actorId'     => $this->actorId,
                'subjectType' => $this->subjectType,
                'subjectId'   => $this->subjectId,
                'trace'       => $this->trace,
                'from'        => null,
                'until'       => null,
                'q'           => $this->q,
            ]);
        } catch (InvalidActivityFilter $exception) {
            if (in_array($exception->field, ['channels', 'severities', 'actions', 'actorType', 'actorId', 'subjectType', 'subjectId', 'trace'], true)) {
                abort(422, $exception->getMessage());
            }

            $this->filterFailed = true;
            $this->filterError = $exception->getMessage();

            return null;
        }
    }

    private function applyRange(ActivityQuery $query): ActivityQuery
    {
        return $query->between(...$this->window());
    }

    private function resetPaging(): void
    {
        $this->page = 1;
        $this->windowEndsAt = null;
        $this->activityCursorSignature = '';
        $this->activityPageCursors = [1 => null];
        $this->pageResult = null;
    }

    private function facetQuery(): ?ActivityQuery
    {
        $filter = $this->filterState();

        if (! $filter instanceof ActivityFilterState) {
            return null;
        }

        return $this->applyRange($filter->applyTo(new ActivityQuery()));
    }

    private function facetSignature(): string
    {
        $filter = $this->filterState();

        if (! $filter instanceof ActivityFilterState) {
            return '';
        }

        return hash('sha256', $filter->signature().'|'.$this->windowKey());
    }

    private function resultCount(): ?int
    {
        if (! Capabilities::supports(Capability::ExactCount)) {
            return null;
        }

        $query = $this->applyRange($this->filterState()?->applyTo(new ActivityQuery()) ?? new ActivityQuery());

        if (null !== $query->search && ! Capabilities::supports(Capability::FullTextSearch)) {
            $query->search = null;
        }

        return app(FacetCounter::class)->total($query, $this->facetSignature(), auth()->user());
    }

    /** @return array<string, int>|null */
    private function channelCounts(): ?array
    {
        $query = $this->facetQuery();

        if (! $query instanceof ActivityQuery) {
            return null;
        }

        $query->channels = [];

        return app(FacetCounter::class)->aggregate($query, 'channel', $this->facetSignature(), auth()->user());
    }

    /** @return array<string, int>|null */
    private function severityCounts(): ?array
    {
        $query = $this->facetQuery();

        if (! $query instanceof ActivityQuery) {
            return null;
        }

        $query->severities = [];

        return app(FacetCounter::class)->aggregate($query, 'severity', $this->facetSignature(), auth()->user());
    }

    /** @return array<string, int>|null */
    private function actionCounts(): ?array
    {
        $query = $this->facetQuery();

        if (! $query instanceof ActivityQuery) {
            return null;
        }

        $query->actions = [];

        return app(FacetCounter::class)->aggregateTop($query, 'action', 8, $this->facetSignature(), auth()->user());
    }

    private function assertPerPageValid(): void
    {
        if (! in_array($this->perPage, self::PER_PAGE_OPTIONS, true)) {
            abort(422, 'The selected page size is invalid.');
        }
    }

    /** @return array{0: DateTimeImmutable, 1: DateTimeImmutable} */
    private function window(): array
    {
        if ('custom' === $this->range) {
            $state = $this->customState();

            if ($state instanceof ActivityFilterState && $state->from instanceof DateTimeImmutable && $state->until instanceof DateTimeImmutable) {
                return [$state->from, $state->until];
            }
        }

        $range = ActivityRange::tryFrom($this->range) ?? ActivityRange::Day;
        $this->windowEndsAt ??= now()->toIso8601String();

        try {
            $endsAt = (new DateTimeImmutable($this->windowEndsAt))->modify('+1 second')->modify('-1 microsecond');
        } catch (Throwable) {
            $endsAt = null;
        }

        return $range->window($endsAt);
    }

    private function customState(): ?ActivityFilterState
    {
        try {
            return ActivityFilterState::fromInput(['from' => $this->from, 'until' => $this->until]);
        } catch (InvalidActivityFilter) {
            return null;
        }
    }

    private function windowKey(): string
    {
        if ('custom' === $this->range) {
            return 'custom:'.($this->from ?? '').'|'.($this->until ?? '');
        }

        $this->windowEndsAt ??= now()->toIso8601String();

        return $this->range.':'.$this->windowEndsAt;
    }

    private function customWindowLabel(): string
    {
        $from = $this->parseDay($this->from);
        $until = $this->parseDay($this->until);

        if ($from instanceof DateTimeImmutable && $until instanceof DateTimeImmutable) {
            $format = $from->format('Y') === $until->format('Y') ? 'j M' : 'j M Y';

            return $from->format($format).' – '.$until->format('j M Y');
        }

        if ($from instanceof DateTimeImmutable) {
            return $from->format('j M Y');
        }

        if ($until instanceof DateTimeImmutable) {
            return $until->format('j M Y');
        }

        return 'Last 24 hours';
    }

    private function parseDay(?string $value): ?DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

        return $date instanceof DateTimeImmutable ? $date : null;
    }

    /** @param list<ActivityEntry> $entries */
    private function earliestOf(array $entries): DateTimeImmutable
    {
        $earliest = $entries[0]->loggedAt;

        foreach ($entries as $entry) {
            if ($entry->loggedAt < $earliest) {
                $earliest = $entry->loggedAt;
            }
        }

        return $earliest;
    }

    /** @param list<ActivityEntry> $entries */
    private function groupHeader(array $entries, DateTimeImmutable $earliest): ActivityEntry
    {
        foreach ($entries as $entry) {
            if (ActivityChannel::Http === $entry->channel) {
                return $entry;
            }
        }

        foreach ($entries as $entry) {
            if ($entry->loggedAt === $earliest) {
                return $entry;
            }
        }

        return $entries[0];
    }

    private function offsetMilliseconds(DateTimeImmutable $at, DateTimeImmutable $from): int
    {
        $delta = (float) $at->format('U.u') - (float) $from->format('U.u');

        return max(0, (int) round($delta * 1000));
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

    private function formatter(): EntryFormatter
    {
        return app(EntryFormatter::class);
    }
}
