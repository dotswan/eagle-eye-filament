<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Filament\Livewire;

use Dotswan\EagleEye\Contracts\Repository\ActivityRepositoryInterface;
use Dotswan\EagleEye\Contracts\Resolution\ActivityEntryPresenterInterface;
use Dotswan\EagleEye\Data\ActivityEntry;
use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Data\Subject;
use Dotswan\EagleEye\Enums\Capability;
use Dotswan\EagleEye\Http\Resources\ActivityEntryResource;
use Dotswan\EagleEyeFilament\Filament\Concerns\AuthorizesActivityAccess;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource;
use Dotswan\EagleEyeFilament\Support\Capabilities;
use Dotswan\EagleEyeFilament\Support\Presentation\ActivityNav;
use Dotswan\EagleEyeFilament\Support\Presentation\ChangeRowsBuilder;
use Dotswan\EagleEyeFilament\Support\Presentation\EntryDetailPresenter;
use Dotswan\EagleEyeFilament\Support\Presentation\EntryFormatter;
use Dotswan\EagleEyeFilament\Support\Trace\TraceReader;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

final class ExplorerPane extends Component
{
    use AuthorizesActivityAccess;
    public const int MAX_SELECTED_ID = 256;

    public const int MAX_PAYLOAD_PREVIEW = 4000;

    #[Url(as: 'selected')]
    public ?string $selectedId = null;

    public ?string $initialId = null;

    public bool $contextLoaded = false;

    private ?ActivityEntry $entry = null;

    private bool $resolved = false;

    /** @var list<ActivityEntry>|null */
    private ?array $contextEntries = null;

    private bool $contextResolved = false;

    private ?int $contextSubjectCount = null;

    private bool $contextSubjectCountExact = true;

    public function mount(?string $initialId = null): void
    {
        $this->authorizeAccess();
        $this->initialId = is_string($initialId) && '' !== $initialId ? $initialId : null;
    }

    public function render(): View
    {
        $this->authorizeAccess();

        return view('eagle-eye-filament::partials.explorer-pane');
    }

    #[On('ee-select')]
    public function select(string $id): void
    {
        $this->authorizeAccess();
        $this->selectedId = $this->assertId($id);
        $this->resetSelection();
        $this->contextLoaded = false;
    }

    public function loadContext(): void
    {
        $this->authorizeAccess();
        $this->contextLoaded = true;
    }

    public function getSelectedEntry(): ?ActivityEntry
    {
        $this->authorizeAccess();

        if ($this->resolved) {
            return $this->entry;
        }

        $this->resolved = true;
        $id = $this->resolveId();

        if (null === $id) {
            return $this->entry = null;
        }

        $found = $this->findEntry($id);

        if (! $found instanceof ActivityEntry && null !== $this->initialId && $this->initialId !== $id) {
            $found = $this->findEntry($this->initialId);
        }

        return $this->entry = $found instanceof ActivityEntry
            ? app(ActivityEntryPresenterInterface::class)->presentOne($found)
            : null;
    }

    /** @return list<array{label: string, kind: string, before: string, after: string, beforeFull: ?string, afterFull: ?string, formattingOnly: bool, truncated: bool, paths: list<array{path: string, before: string, after: string}>}> */
    public function getChangeRows(): array
    {
        $this->authorizeAccess();
        $entry = $this->getSelectedEntry();

        if (! $entry instanceof ActivityEntry) {
            return [];
        }

        $presented = (new ActivityEntryResource($entry))->toArray(Request::create('/', 'GET'));
        $changes = $presented['changes'] ?? [];

        if (! is_array($changes)) {
            return [];
        }

        return app(ChangeRowsBuilder::class)->build($changes);
    }

    /** @return list<array{title: string, rows: list<array{label: string, value: string, mono: bool}>}> */
    public function getDetailSections(): array
    {
        $this->authorizeAccess();
        $entry = $this->getSelectedEntry();

        return $entry instanceof ActivityEntry ? app(EntryDetailPresenter::class)->sections($entry) : [];
    }

    public function getPayloadPreview(): string
    {
        $this->authorizeAccess();
        $entry = $this->getSelectedEntry();

        if (! $entry instanceof ActivityEntry) {
            return '';
        }

        $json = json_encode($this->formatter()->displayPayload($entry->payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        if (! is_string($json)) {
            return '';
        }

        return mb_strlen($json) > self::MAX_PAYLOAD_PREVIEW ? mb_substr($json, 0, self::MAX_PAYLOAD_PREVIEW).'…' : $json;
    }

    public function getPayloadSizeLabel(): string
    {
        $this->authorizeAccess();
        $entry = $this->getSelectedEntry();

        return $entry instanceof ActivityEntry ? $entry->payloadBytes.' B' : '';
    }

    public function getSealedCount(): int
    {
        $this->authorizeAccess();
        $entry = $this->getSelectedEntry();

        return $entry instanceof ActivityEntry ? count($entry->secrets) : 0;
    }

    public function getFullPageUrl(): ?string
    {
        $this->authorizeAccess();
        $entry = $this->getSelectedEntry();

        return $entry instanceof ActivityEntry ? ActivityLogResource::getUrl('view', ['record' => $entry->id]) : null;
    }

    /** @return list<array{label: string, value: string, mono: bool}> */
    public function getOrigin(): array
    {
        $this->authorizeAccess();
        $entry = $this->getSelectedEntry();

        if (! $entry instanceof ActivityEntry) {
            return [];
        }

        $rows = [];

        if (null !== $entry->request?->ip) {
            $rows[] = ['label' => 'IP', 'value' => $entry->request->ip, 'mono' => true];
        }

        if (null !== $entry->request?->userAgent) {
            $rows[] = ['label' => 'Agent', 'value' => $entry->request->userAgent, 'mono' => false];
        }

        return $rows;
    }

    public function getTraceUrl(): ?string
    {
        $this->authorizeAccess();
        $entry = $this->getSelectedEntry();

        return $entry instanceof ActivityEntry ? ActivityNav::traceUrl($entry->trace->traceId) : null;
    }

    public function getSubjectHistoryUrl(): ?string
    {
        $this->authorizeAccess();
        $entry = $this->getSelectedEntry();

        return $entry instanceof ActivityEntry ? ActivityNav::subjectUrl($entry->subject) : null;
    }

    /** @return list<ActivityEntry> */
    public function getContextSiblings(): array
    {
        $this->authorizeAccess();

        if ($this->contextResolved) {
            return $this->contextEntries ?? [];
        }

        $this->contextResolved = true;
        $entry = $this->getSelectedEntry();

        if (! $entry instanceof ActivityEntry) {
            return $this->contextEntries = [];
        }

        $traceId = $entry->trace->traceId;

        if (1 !== preg_match('/^[a-f0-9]{32}$/', $traceId)) {
            return $this->contextEntries = [];
        }

        return $this->contextEntries = app(TraceReader::class)->siblings($traceId, $entry->id, 2);
    }

    public function getSubjectCount(): ?int
    {
        $this->authorizeAccess();
        $entry = $this->getSelectedEntry();

        if (! $entry instanceof ActivityEntry || ! $entry->subject instanceof Subject) {
            return null;
        }

        if (null !== $this->contextSubjectCount) {
            return $this->contextSubjectCount;
        }

        $this->contextSubjectCountExact = Capabilities::supports(Capability::ExactCount);

        try {
            return $this->contextSubjectCount = app(ActivityRepositoryInterface::class)->count(
                (new ActivityQuery())->forSubject($entry->subject->type, $entry->subject->id),
            );
        } catch (Throwable) {
            return null;
        }
    }

    public function isSubjectCountExact(): bool
    {
        return $this->contextSubjectCountExact;
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

    private function resolveId(): ?string
    {
        if (null !== $this->selectedId && '' !== $this->selectedId) {
            return $this->assertId($this->selectedId);
        }

        return $this->initialId;
    }

    private function findEntry(string $id): ?ActivityEntry
    {
        if (mb_strlen($id) > self::MAX_SELECTED_ID || 1 === preg_match('/[\x00-\x1F\x7F]/', $id)) {
            return null;
        }

        $entry = app(ActivityRepositoryInterface::class)->find($id);

        return $entry instanceof ActivityEntry && $entry->id === $id ? $entry : null;
    }

    private function assertId(string $id): string
    {
        if (mb_strlen($id) > self::MAX_SELECTED_ID || 1 === preg_match('/[\x00-\x1F\x7F]/', $id)) {
            abort(422, 'The selected activity id is invalid.');
        }

        return $id;
    }

    private function resetSelection(): void
    {
        $this->resolved = false;
        $this->entry = null;
        $this->contextResolved = false;
        $this->contextEntries = null;
        $this->contextSubjectCount = null;
    }

    private function formatter(): EntryFormatter
    {
        return app(EntryFormatter::class);
    }
}
