<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Support\Trace;

use DateTimeImmutable;
use Dotswan\EagleEye\Contracts\Repository\ActivityRepositoryInterface;
use Dotswan\EagleEye\Contracts\Resolution\ActivityEntryPresenterInterface;
use Dotswan\EagleEye\Data\ActivityEntry;
use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Data\TraceTree;
use Dotswan\EagleEye\Enums\SortOrder;

final class TraceReader
{
    public const int MAX_ENTRIES = 50;
    public const int DEFAULT_RADIUS = 2;

    /** @var array<string, TraceSnapshot> */
    private array $memo = [];

    public function __construct(
        private readonly ActivityRepositoryInterface $repository,
        private readonly ActivityEntryPresenterInterface $presenter,
    ) {
    }

    public function read(string $traceId): TraceSnapshot
    {
        if (isset($this->memo[$traceId])) {
            return $this->memo[$traceId];
        }

        $result = $this->repository->search(
            (new ActivityQuery())->forTrace($traceId)->orderByTime(SortOrder::Ascending)->limit(self::MAX_ENTRIES),
        );
        $entries = $this->presenter->present($result->entries);

        if ($result->hasMore) {
            $root = $this->rootEntry($traceId, $entries);

            if ($root instanceof ActivityEntry) {
                $entries[] = $root;
            }
        }

        return $this->memo[$traceId] = new TraceSnapshot(
            traceId: $traceId,
            entries: $entries,
            roots: TraceTree::assemble($entries),
            incomplete: $result->hasMore,
            t0: $this->earliest($entries),
        );
    }

    /**
     * @param  list<ActivityEntry>  $entries
     */
    private function rootEntry(string $traceId, array $entries): ?ActivityEntry
    {
        $root = $this->latestRaw($traceId, ['http.request', 'console.finished'])
            ?? $this->latestRaw($traceId, ['queue.processed', 'queue.failed']);

        if (! $root instanceof ActivityEntry) {
            return null;
        }

        foreach ($entries as $entry) {
            if ($entry->id === $root->id) {
                return null;
            }
        }

        $presented = $this->presenter->present([$root]);

        return $presented[0] ?? null;
    }

    /**
     * @param  list<string>  $actions
     */
    private function latestRaw(string $traceId, array $actions): ?ActivityEntry
    {
        $result = $this->repository->search(
            (new ActivityQuery())
                ->forTrace($traceId)
                ->actions(...$actions)
                ->orderByTime(SortOrder::Descending)
                ->limit(1),
        );
        $entry = $result->entries[0] ?? null;

        return $entry instanceof ActivityEntry ? $entry : null;
    }

    /** @return array{prev: ?ActivityEntry, next: ?ActivityEntry} */
    public function neighbors(string $traceId, string $entryId): array
    {
        $entries = $this->read($traceId)->entries;
        $index = $this->indexOf($entries, $entryId);

        if (null === $index) {
            return ['prev' => null, 'next' => null];
        }

        return [
            'prev' => $entries[$index - 1] ?? null,
            'next' => $entries[$index + 1] ?? null,
        ];
    }

    /** @return list<ActivityEntry> */
    public function siblings(string $traceId, string $entryId, int $radius = self::DEFAULT_RADIUS): array
    {
        $entries = $this->read($traceId)->entries;
        $index = $this->indexOf($entries, $entryId);

        if (null === $index) {
            return [];
        }

        $radius = max(0, $radius);
        $siblings = array_slice($entries, max(0, $index - $radius), $radius * 2 + 1);

        return array_values(array_filter(
            $siblings,
            static fn (ActivityEntry $entry): bool => $entry->id !== $entryId,
        ));
    }

    /**
     * @param  list<ActivityEntry>  $entries
     */
    private function earliest(array $entries): ?DateTimeImmutable
    {
        $earliest = null;

        foreach ($entries as $entry) {
            if (! $earliest instanceof DateTimeImmutable || $entry->loggedAt < $earliest) {
                $earliest = $entry->loggedAt;
            }
        }

        return $earliest;
    }

    /**
     * @param  list<ActivityEntry>  $entries
     */
    private function indexOf(array $entries, string $entryId): ?int
    {
        foreach ($entries as $index => $entry) {
            if ($entry->id === $entryId) {
                return $index;
            }
        }

        return null;
    }
}
