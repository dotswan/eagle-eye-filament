<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource\Pages;

use Dotswan\EagleEye\Contracts\Repository\ActivityRepositoryInterface;
use Dotswan\EagleEye\Contracts\Resolution\ActivityEntryPresenterInterface;
use Dotswan\EagleEye\Contracts\Security\LogAccessPolicyInterface;
use Dotswan\EagleEye\Data\ActivityEntry;
use Dotswan\EagleEye\Data\Actor;
use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Data\Subject;
use Dotswan\EagleEye\Enums\SortOrder;
use Dotswan\EagleEye\Exceptions\SecretRevealDeniedException;
use Dotswan\EagleEye\Http\Resources\ActivityEntryResource;
use Dotswan\EagleEyeFilament\Filament\Concerns\AuthorizesActivityAccess;
use Dotswan\EagleEyeFilament\Filament\Concerns\HasFullContentWidth;
use Dotswan\EagleEyeFilament\Filament\Pages\SubjectHistoryPage;
use Dotswan\EagleEyeFilament\Filament\Pages\TraceTimelinePage;
use Dotswan\EagleEyeFilament\Filament\Pages\UserActivityPage;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource;
use Dotswan\EagleEyeFilament\Support\Presentation\ActivityNav;
use Dotswan\EagleEyeFilament\Support\Presentation\ChangeRowsBuilder;
use Dotswan\EagleEyeFilament\Support\Presentation\EntryDetailPresenter;
use Dotswan\EagleEyeFilament\Support\Presentation\EntryFormatter;
use Dotswan\EagleEyeFilament\Support\Trace\TraceReader;
use Dotswan\EagleEyeFilament\Support\Trace\TraceSnapshot;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Livewire\Attributes\Locked;

final class ViewActivityLog extends Page
{
    use AuthorizesActivityAccess;
    use HasFullContentWidth;

    public const int MAX_RELATED_SUBJECT_ENTRIES = 4;

    protected static string $resource = ActivityLogResource::class;

    protected string $view = 'eagle-eye-filament::pages.activity-detail';

    #[Locked]
    public string $recordId = '';

    /** @var array<string, string> */
    private array $revealed = [];

    private ?ActivityEntry $entry = null;

    private ?TraceSnapshot $trace = null;

    /** @var list<ActivityEntry>|null */
    private ?array $relatedSubjectEntries = null;

    public function mount(string $record): void
    {
        $policy = app(LogAccessPolicyInterface::class);

        abort_unless($policy->canView(auth()->user()), 403);
        abort_unless('' !== $record && mb_strlen($record) <= 256 && 0 === preg_match('/[\x00-\x1F\x7F]/', $record), 404);

        $entry = app(ActivityRepositoryInterface::class)->find($record);

        abort_if(null === $entry || $entry->id !== $record, 404);

        $this->recordId = $record;
    }

    public function getTitle(): string
    {
        $this->authorizeAccess();

        return $this->getEntry()->title;
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

    /** @return list<array{label: string, href?: string}> */
    public function getBreadcrumbItems(): array
    {
        $this->authorizeAccess();
        $entry = $this->getEntry();
        $items = [['label' => 'Explorer', 'href' => ListActivityLogs::getUrl()]];
        $traceUrl = $this->getTraceUrl();

        if (null !== $traceUrl) {
            $items[] = ['label' => mb_substr($entry->trace->traceId, 0, 8), 'href' => $traceUrl];
        }

        $items[] = ['label' => $entry->id];

        return $items;
    }

    public function revealSecret(string $path): void
    {
        $this->authorizeAccess();

        if (mb_strlen($path) > 256 || 1 !== preg_match('/^[A-Za-z0-9_.-]+$/', $path)) {
            abort(422, 'The secret path is invalid.');
        }

        try {
            $this->revealed[$path] = app(ActivityRepositoryInterface::class)->reveal($this->getEntry(), $path, auth()->user());

            Notification::make()->title('Secret revealed. This was recorded in the audit log.')->success()->send();
        } catch (SecretRevealDeniedException) {
            Notification::make()->title('You are not allowed to reveal secrets.')->danger()->send();
        }
    }

    /** @return list<string> */
    public function getSealedPaths(): array
    {
        $this->authorizeAccess();

        return array_keys($this->getEntry()->secrets);
    }

    public function getRevealed(string $path): ?string
    {
        $this->authorizeAccess();

        return $this->revealed[$path] ?? null;
    }

    public function getTraceUrl(): ?string
    {
        $this->authorizeAccess();
        $traceId = $this->getEntry()->trace->traceId;

        return '' === $traceId ? null : TraceTimelinePage::getUrl(['trace' => $traceId]);
    }

    public function getEntry(): ActivityEntry
    {
        $this->authorizeAccess();

        if ($this->entry instanceof ActivityEntry && $this->entry->id === $this->recordId) {
            return $this->entry;
        }

        $entry = app(ActivityRepositoryInterface::class)->find($this->recordId);

        abort_if(null === $entry || $entry->id !== $this->recordId, 404);

        return $this->entry = app(ActivityEntryPresenterInterface::class)->presentOne($entry);
    }

    public function getTrace(): TraceSnapshot
    {
        $this->authorizeAccess();

        if ($this->trace instanceof TraceSnapshot) {
            return $this->trace;
        }

        $traceId = $this->getEntry()->trace->traceId;

        if (1 !== preg_match('/^[a-f0-9]{32}$/', $traceId) || $traceId === str_repeat('0', 32)) {
            return $this->trace = new TraceSnapshot($traceId, [], [], false, null);
        }

        return $this->trace = $this->traceReader()->read($traceId);
    }

    /** @return list<ActivityEntry> */
    public function getTraceSiblings(): array
    {
        $this->authorizeAccess();
        $snapshot = $this->getTrace();

        if ('' === $snapshot->traceId) {
            return [];
        }

        return $this->traceReader()->siblings($snapshot->traceId, $this->getEntry()->id, TraceReader::DEFAULT_RADIUS);
    }

    /** @return array{prev: ?string, next: ?string} */
    public function getNeighbors(): array
    {
        $this->authorizeAccess();
        $snapshot = $this->getTrace();

        if ('' === $snapshot->traceId) {
            return ['prev' => null, 'next' => null];
        }

        $neighbors = $this->traceReader()->neighbors($snapshot->traceId, $this->getEntry()->id);

        return [
            'prev' => $neighbors['prev'] instanceof ActivityEntry ? ActivityLogResource::getUrl('view', ['record' => $neighbors['prev']->id]) : null,
            'next' => $neighbors['next'] instanceof ActivityEntry ? ActivityLogResource::getUrl('view', ['record' => $neighbors['next']->id]) : null,
        ];
    }

    public function traceWindowExceeded(): bool
    {
        $this->authorizeAccess();
        $snapshot = $this->getTrace();

        if (! $snapshot->incomplete || '' === $snapshot->traceId) {
            return false;
        }

        $entryId = $this->getEntry()->id;

        foreach ($snapshot->entries as $traceEntry) {
            if ($traceEntry->id === $entryId) {
                return false;
            }
        }

        return true;
    }

    /** @return list<ActivityEntry> */
    public function getRelatedSubjectEntries(): array
    {
        $this->authorizeAccess();

        if (null !== $this->relatedSubjectEntries) {
            return $this->relatedSubjectEntries;
        }

        $entry = $this->getEntry();
        $subject = $entry->subject;

        if (! $subject instanceof Subject || '' === $subject->id) {
            return $this->relatedSubjectEntries = [];
        }

        $result = $this->repository()->search(
            (new ActivityQuery())->forSubject($subject->type, $subject->id)->orderByTime(SortOrder::Descending)->limit(5),
        );

        $related = [];

        foreach ($result->entries as $relatedEntry) {
            if ($relatedEntry->id === $entry->id) {
                continue;
            }

            $related[] = $relatedEntry;
        }

        return $this->relatedSubjectEntries = array_slice($related, 0, self::MAX_RELATED_SUBJECT_ENTRIES);
    }

    public function getSubjectUrl(): ?string
    {
        $this->authorizeAccess();
        $subject = $this->getEntry()->subject;

        if (! $subject instanceof Subject || '' === $subject->id) {
            return null;
        }

        return SubjectHistoryPage::getUrl(['subject_type' => $subject->type, 'subject_id' => $subject->id]);
    }

    public function getActorUrl(): ?string
    {
        $this->authorizeAccess();
        $actor = $this->getEntry()->actor;

        if (! $actor instanceof Actor || null === $actor->id || '' === $actor->id) {
            return null;
        }

        return UserActivityPage::getUrl(['actor_type' => $actor->type, 'actor_id' => $actor->id]);
    }

    public function getPayloadJson(): string
    {
        $this->authorizeAccess();
        $json = json_encode(
            app(EntryFormatter::class)->displayPayload($this->getEntry()->payload),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );

        return is_string($json) ? $json : '';
    }

    public function getPayloadSizeLabel(): string
    {
        $this->authorizeAccess();

        return $this->getEntry()->payloadBytes.' B';
    }

    /** @return list<array{label: string, kind: string, before: string, after: string, beforeFull: ?string, afterFull: ?string, formattingOnly: bool, truncated: bool, paths: list<array{path: string, before: string, after: string}>}> */
    public function getChangeRows(): array
    {
        $this->authorizeAccess();

        return app(ChangeRowsBuilder::class)->build($this->getChanges());
    }

    /** @return list<array{title: string, rows: list<array{label: string, value: string, mono: bool}>}> */
    public function getDetailSections(): array
    {
        $this->authorizeAccess();

        return app(EntryDetailPresenter::class)->sections($this->getEntry());
    }


    /** @return list<array{path: string, label: string, before: mixed, after: mixed}> */
    public function getChanges(): array
    {
        $resource = new ActivityEntryResource($this->getEntry());
        $presented = $resource->toArray(request());
        $changes = $presented['changes'] ?? [];

        if (! is_array($changes)) {
            return [];
        }

        /** @var list<array{path: string, label: string, before: mixed, after: mixed}> $changes */
        return $changes;
    }

    private function repository(): ActivityRepositoryInterface
    {
        return app(ActivityRepositoryInterface::class);
    }

    private function traceReader(): TraceReader
    {
        return app(TraceReader::class);
    }
}
