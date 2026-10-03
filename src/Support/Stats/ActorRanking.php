<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Support\Stats;

use Dotswan\EagleEye\Contracts\Repository\ActivityRepositoryInterface;
use Dotswan\EagleEye\Contracts\Resolution\ActivityIdentityResolverInterface;
use Dotswan\EagleEye\Data\Actor;
use Dotswan\EagleEye\Data\Query\ActivityQuery;

final readonly class ActorRanking
{
    public function __construct(
        private ActivityRepositoryInterface $repository,
        private ActivityIdentityResolverInterface $resolver,
    ) {
    }

    /**
     * @return list<array{actor: Actor, key: string, events: int}>
     */
    public function top(ActivityQuery $query, int $limit): array
    {
        $groups = $this->repository->aggregateTop($query, 'actor', $limit);
        $actors = [];

        foreach (array_keys($groups) as $key) {
            $identity = json_decode($key, true);

            if (is_array($identity) && isset($identity[0], $identity[1]) && is_string($identity[0]) && is_string($identity[1])) {
                $actors[$key] = new Actor($identity[0], '' === $identity[1] ? null : $identity[1]);
            }
        }

        $actors = $this->resolver->resolveActors($actors);
        $ranking = [];

        foreach ($groups as $key => $events) {
            $ranking[] = [
                'actor'  => $actors[$key] ?? new Actor($key),
                'key'    => $key,
                'events' => $events,
            ];
        }

        return $ranking;
    }
}
