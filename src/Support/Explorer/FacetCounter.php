<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Support\Explorer;

use Dotswan\EagleEye\Contracts\Repository\ActivityRepositoryInterface;
use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Throwable;

final readonly class FacetCounter
{
    private const int TTL_SECONDS = 60;

    public function __construct(private ActivityRepositoryInterface $repository)
    {
    }

    /** @return array<string, int>|null */
    public function aggregate(ActivityQuery $query, string $groupBy, string $signature, ?object $viewer): ?array
    {
        $key = $this->key('aggregate:'.$groupBy, $signature, $viewer);
        $cached = Cache::get($key);

        if (false === $cached) {
            return null;
        }

        if (is_array($cached)) {
            return $this->intMap($cached);
        }

        try {
            $value = $this->repository->aggregate($query, $groupBy);
        } catch (Throwable $exception) {
            report($exception);
            Cache::put($key, false, self::TTL_SECONDS);

            return null;
        }

        Cache::put($key, $value, self::TTL_SECONDS);

        return $value;
    }

    /** @return array<string, int>|null */
    public function aggregateTop(ActivityQuery $query, string $groupBy, int $limit, string $signature, ?object $viewer): ?array
    {
        $key = $this->key('top:'.$groupBy.':'.$limit, $signature, $viewer);
        $cached = Cache::get($key);

        if (false === $cached) {
            return null;
        }

        if (is_array($cached)) {
            return $this->intMap($cached);
        }

        try {
            $value = $this->repository->aggregateTop($query, $groupBy, $limit);
        } catch (Throwable $exception) {
            report($exception);
            Cache::put($key, false, self::TTL_SECONDS);

            return null;
        }

        Cache::put($key, $value, self::TTL_SECONDS);

        return $value;
    }

    public function total(ActivityQuery $query, string $signature, ?object $viewer): ?int
    {
        $key = $this->key('total', $signature, $viewer);
        $cached = Cache::get($key);

        if (false === $cached) {
            return null;
        }

        if (is_int($cached)) {
            return $cached;
        }

        try {
            $value = $this->repository->count($query);
        } catch (Throwable $exception) {
            report($exception);
            Cache::put($key, false, self::TTL_SECONDS);

            return null;
        }

        Cache::put($key, $value, self::TTL_SECONDS);

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<string, int>
     */
    private function intMap(array $value): array
    {
        $mapped = [];

        foreach ($value as $key => $item) {
            $mapped[(string) $key] = (int) $item;
        }

        return $mapped;
    }

    private function key(string $kind, string $signature, ?object $viewer): string
    {
        $viewerKey = $viewer instanceof Authenticatable
            ? [$viewer::class, $viewer->getAuthIdentifier()]
            : [null, null];

        return 'eagle-eye:facets:'.hash('sha256', serialize([$viewerKey, $signature, $kind]));
    }
}
