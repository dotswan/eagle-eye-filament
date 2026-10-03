<?php

declare(strict_types=1);

use Dotswan\EagleEye\Data\ActivityEntry;
use Dotswan\EagleEye\Contracts\Repository\ActivityRepositoryInterface;
use Dotswan\EagleEye\Contracts\Resolution\ActivityEntryPresenterInterface;
use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Data\Query\QueryResult;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEyeFilament\Support\Trace\TraceReader;
use Dotswan\EagleEyeFilament\Tests\Support\EntryFactory;
use Dotswan\EagleEyeFilament\Tests\TestCase;

uses(TestCase::class);

it('reads a trace once and memoizes the snapshot', function (): void {
    $trace = str_repeat('a', 32);
    $root = EntryFactory::make('01J0000000000000000000R1', $trace, new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC')), channel: ActivityChannel::Http);
    $child = EntryFactory::make('01J0000000000000000000C1', $trace, new DateTimeImmutable('2026-01-01 12:00:01', new DateTimeZone('UTC')), causationId: $root->id);
    $repository = Mockery::mock(ActivityRepositoryInterface::class);
    $repository->shouldReceive('search')->once()->andReturn(new QueryResult([$root, $child], null, false, null, true));
    $presenter = Mockery::mock(ActivityEntryPresenterInterface::class);
    $presenter->shouldReceive('present')->once()->andReturn([$root, $child]);
    app()->instance(ActivityRepositoryInterface::class, $repository);
    app()->instance(ActivityEntryPresenterInterface::class, $presenter);
    $reader = app(TraceReader::class);

    $snapshot = $reader->read($trace);

    expect($snapshot->entries)->toHaveCount(2)
        ->and($snapshot->t0?->format('Y-m-d H:i:s'))->toBe('2026-01-01 12:00:00')
        ->and($snapshot->roots)->toHaveCount(1)
        ->and($snapshot->incomplete)->toBeFalse()
        ->and($reader->read($trace))->toBe($snapshot);
});

it('resolves neighbors and bounded siblings by entry id', function (): void {
    $trace = str_repeat('c', 32);
    $first = EntryFactory::make('01J0000000000000000000N1', $trace, new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC')));
    $second = EntryFactory::make('01J0000000000000000000N2', $trace, new DateTimeImmutable('2026-01-01 12:00:01', new DateTimeZone('UTC')));
    $third = EntryFactory::make('01J0000000000000000000N3', $trace, new DateTimeImmutable('2026-01-01 12:00:02', new DateTimeZone('UTC')));
    $repository = Mockery::mock(ActivityRepositoryInterface::class);
    $repository->shouldReceive('search')->once()->andReturn(new QueryResult([$first, $second, $third], null, false, null, true));
    $presenter = Mockery::mock(ActivityEntryPresenterInterface::class);
    $presenter->shouldReceive('present')->once()->andReturn([$first, $second, $third]);
    app()->instance(ActivityRepositoryInterface::class, $repository);
    app()->instance(ActivityEntryPresenterInterface::class, $presenter);
    $reader = app(TraceReader::class);

    $neighbors = $reader->neighbors($trace, $second->id);
    $siblings = $reader->siblings($trace, $second->id, 1);

    expect($neighbors['prev']?->id)->toBe($first->id)
        ->and($neighbors['next']?->id)->toBe($third->id)
        ->and(array_map(static fn (ActivityEntry $entry): string => $entry->id, $siblings))->toBe([$first->id, $third->id]);
});

it('reports an incomplete bounded read', function (): void {
    $trace = str_repeat('d', 32);
    $entry = EntryFactory::make('01J0000000000000000000X1', $trace);
    $repository = Mockery::mock(ActivityRepositoryInterface::class);
    $repository->shouldReceive('search')->twice()->andReturn(
        new QueryResult([$entry], null, true, null, true),
        new QueryResult([$entry], null, false, null, true),
    );
    $presenter = Mockery::mock(ActivityEntryPresenterInterface::class);
    $presenter->shouldReceive('present')->once()->andReturn([$entry]);
    app()->instance(ActivityRepositoryInterface::class, $repository);
    app()->instance(ActivityEntryPresenterInterface::class, $presenter);

    expect(app(TraceReader::class)->read($trace)->incomplete)->toBeTrue();
});

it('appends the trace root when the bounded window is exceeded', function (): void {
    $trace = str_repeat('e', 32);
    $utc = new DateTimeZone('UTC');
    $entries = [];

    for ($index = 0; $index < 50; $index++) {
        $entries[] = EntryFactory::make(
            mb_str_pad('01J'.$index, 26, '0', STR_PAD_LEFT),
            $trace,
            new DateTimeImmutable('2026-01-01 12:00:'.$index, $utc),
        );
    }

    $root = EntryFactory::make(mb_str_pad('01JROOT', 26, '0', STR_PAD_LEFT), $trace, new DateTimeImmutable('2026-01-01 12:05:00', $utc), channel: ActivityChannel::Http);
    $repository = Mockery::mock(ActivityRepositoryInterface::class);
    $repository->shouldReceive('search')->twice()->andReturn(
        new QueryResult($entries, null, true, null, true),
        new QueryResult([$root], null, false, null, true),
    );
    $presenter = Mockery::mock(ActivityEntryPresenterInterface::class);
    $presenter->shouldReceive('present')->twice()->andReturn($entries, [$root]);
    app()->instance(ActivityRepositoryInterface::class, $repository);
    app()->instance(ActivityEntryPresenterInterface::class, $presenter);

    $snapshot = app(TraceReader::class)->read($trace);

    expect($snapshot->entries)->toHaveCount(51)
        ->and($snapshot->entries[50]->id)->toBe($root->id)
        ->and($snapshot->incomplete)->toBeTrue();
});

it('prefers the http request root over a later async queue entry', function (): void {
    $trace = str_repeat('f', 32);
    $utc = new DateTimeZone('UTC');
    $entries = [];

    for ($index = 0; $index < 50; $index++) {
        $entries[] = EntryFactory::make(
            mb_str_pad('01J'.$index, 26, '0', STR_PAD_LEFT),
            $trace,
            new DateTimeImmutable('2026-01-01 12:00:'.$index, $utc),
        );
    }

    $httpRoot = EntryFactory::make(mb_str_pad('01JHTTP', 26, '0', STR_PAD_LEFT), $trace, new DateTimeImmutable('2026-01-01 11:59:59', $utc), channel: ActivityChannel::Http);
    $queueRoot = EntryFactory::make(mb_str_pad('01JQUEUE', 26, '0', STR_PAD_LEFT), $trace, new DateTimeImmutable('2026-01-01 12:06:00', $utc), channel: ActivityChannel::Queue);
    $repository = Mockery::mock(ActivityRepositoryInterface::class);
    $repository->shouldReceive('search')->andReturnUsing(function (ActivityQuery $query) use ($entries, $httpRoot, $queueRoot): QueryResult {
        if (in_array('http.request', $query->actions, true)) {
            return new QueryResult([$httpRoot], null, false, null, true);
        }

        if (in_array('queue.processed', $query->actions, true) || in_array('queue.failed', $query->actions, true)) {
            return new QueryResult([$queueRoot], null, false, null, true);
        }

        return new QueryResult($entries, null, true, null, true);
    });
    $presenter = Mockery::mock(ActivityEntryPresenterInterface::class);
    $presenter->shouldReceive('present')->andReturnUsing(static fn (array $items): array => $items);
    app()->instance(ActivityRepositoryInterface::class, $repository);
    app()->instance(ActivityEntryPresenterInterface::class, $presenter);

    $snapshot = app(TraceReader::class)->read($trace);

    expect($snapshot->entries)->toHaveCount(51)
        ->and($snapshot->entries[50]->id)->toBe($httpRoot->id);
});
