<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Tests\Support;

use InvalidArgumentException;
use LengthException;
use LogicException;
use Dotswan\EagleEye\Contracts\Repository\ActivityRepositoryInterface;
use Dotswan\EagleEye\Data\ActivityEntry;
use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Data\Query\QueryResult;
use DateTimeImmutable;

final class CallCountingRepository implements ActivityRepositoryInterface
{
    public int $searches = 0;

    public int $finds = 0;

    public int $counts = 0;

    public int $aggregates = 0;

    public int $aggregateTops = 0;

    public int $series = 0;

    public int $reveals = 0;

    public ?ActivityQuery $lastSearchQuery = null;

    public ?ActivityQuery $lastAggregateQuery = null;

    public function __construct(
        private readonly ActivityRepositoryInterface $inner,
        public bool $failAggregates = false,
        public bool $failAggregatesWithLength = false,
        public bool $failSeries = false,
    ) {
    }

    public function find(string $id): ?ActivityEntry
    {
        $this->finds++;

        return $this->inner->find($id);
    }

    public function search(ActivityQuery $query): QueryResult
    {
        $this->searches++;
        $this->lastSearchQuery = $query;

        return $this->inner->search($query);
    }

    public function count(ActivityQuery $query): int
    {
        $this->counts++;
        $this->lastAggregateQuery = $query;

        return $this->inner->count($query);
    }

    /** @return array<string, int> */
    public function aggregate(ActivityQuery $query, string $groupBy, string $interval = 'day'): array
    {
        $this->aggregates++;
        $this->lastAggregateQuery = $query;

        if ($this->failAggregatesWithLength) {
            throw new LengthException('Activity aggregation exceeds its group limit.');
        }

        if ($this->failAggregates) {
            throw new InvalidArgumentException('Unsupported aggregate group.');
        }

        return $this->inner->aggregate($query, $groupBy, $interval);
    }

    /** @return array<string, int> */
    public function aggregateTop(ActivityQuery $query, string $groupBy, int $limit): array
    {
        $this->aggregateTops++;
        $this->lastAggregateQuery = $query;

        if ($this->failAggregates) {
            throw new InvalidArgumentException('Unsupported aggregate group.');
        }

        return $this->inner->aggregateTop($query, $groupBy, $limit);
    }

    /** @return array{buckets: list<DateTimeImmutable>, series: array<string, list<int>>} */
    public function aggregateSeries(ActivityQuery $query, ?string $groupBy, int $stepSeconds): array
    {
        $this->series++;

        if ($this->failSeries) {
            throw new LogicException('The active driver does not support series aggregation.');
        }

        return $this->inner->aggregateSeries($query, $groupBy, $stepSeconds);
    }

    /** @return list<string> */
    public function tags(?string $prefix = null, int $limit = 50): array
    {
        return $this->inner->tags($prefix, $limit);
    }

    public function reveal(ActivityEntry $entry, string $path, ?object $viewer = null): string
    {
        $this->reveals++;

        return $this->inner->reveal($entry, $path, $viewer);
    }
}
