<?php

declare(strict_types=1);

use Dotswan\EagleEye\Contracts\Repository\ActivityRepositoryInterface;
use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEyeFilament\Support\Explorer\FacetCounter;

it('degrades to an unavailable state when facet aggregation throws', function (): void {
    $repository = Mockery::mock(ActivityRepositoryInterface::class);
    $repository->shouldReceive('aggregate')->once()->andThrow(new RuntimeException('driver outage'));
    $repository->shouldReceive('aggregateTop')->once()->andThrow(new RuntimeException('driver outage'));
    $repository->shouldReceive('count')->once()->andThrow(new RuntimeException('driver outage'));

    app()->instance(ActivityRepositoryInterface::class, $repository);

    $counter = new FacetCounter(app(ActivityRepositoryInterface::class));
    $query = new ActivityQuery();

    expect($counter->aggregate($query, 'channel', 'outage-signature', null))->toBeNull()
        ->and($counter->aggregateTop($query, 'action', 8, 'outage-signature', null))->toBeNull()
        ->and($counter->total($query, 'outage-signature', null))->toBeNull();
});
