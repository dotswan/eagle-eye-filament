<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Tests\Support;

use DateTimeImmutable;
use Dotswan\EagleEye\Contracts\Driver\ActivityLogSeriesDriverInterface;
use Dotswan\EagleEye\Contracts\Driver\FullActivityLogDriverInterface;
use Dotswan\EagleEye\Data\ActivityEntry;
use Dotswan\EagleEye\Data\ErasureResult;
use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Data\Query\QueryResult;
use Dotswan\EagleEye\Enums\Capability;
use LogicException;

final readonly class CapabilityFakingDriver implements FullActivityLogDriverInterface, ActivityLogSeriesDriverInterface
{
    /**
     * @param  list<Capability>  $supported
     */
    public function __construct(
        private FullActivityLogDriverInterface $inner,
        private array $supported,
    ) {
    }

    public function write(ActivityEntry $entry): void
    {
        $this->inner->write($entry);
    }

    public function writeBatch(array $entries): array
    {
        return $this->inner->writeBatch($entries);
    }
    public function name(): string
    {
        return $this->inner->name();
    }

    public function healthy(): bool
    {
        return $this->inner->healthy();
    }

    public function supports(Capability $capability): bool
    {
        return in_array($capability, $this->supported, true);
    }

    public function find(string $id): ?ActivityEntry
    {
        return $this->inner->find($id);
    }

    public function search(ActivityQuery $query): QueryResult
    {
        return $this->inner->search($query);
    }

    public function count(ActivityQuery $query): int
    {
        return $this->inner->count($query);
    }

    public function aggregate(ActivityQuery $query, string $groupBy, string $interval = 'day'): array
    {
        return $this->inner->aggregate($query, $groupBy, $interval);
    }

    public function aggregateTop(ActivityQuery $query, string $groupBy, int $limit): array
    {
        return $this->inner->aggregateTop($query, $groupBy, $limit);
    }

    public function tags(?string $prefix = null, int $limit = 50): array
    {
        return $this->inner->tags($prefix, $limit);
    }

    public function aggregateSeries(ActivityQuery $query, ?string $groupBy, int $stepSeconds): array
    {
        if (! $this->inner instanceof ActivityLogSeriesDriverInterface) {
            throw new LogicException('The wrapped driver does not support series aggregation.');
        }

        return $this->inner->aggregateSeries($query, $groupBy, $stepSeconds);
    }

    public function prune(DateTimeImmutable $before, ?ActivityQuery $scope = null): int
    {
        return $this->inner->prune($before, $scope);
    }

    public function forget(string $subjectType, string $subjectId): ErasureResult
    {
        return $this->inner->forget($subjectType, $subjectId);
    }

    public function install(): void
    {
        $this->inner->install();
    }

    public function stats(): array
    {
        return $this->inner->stats();
    }
}
