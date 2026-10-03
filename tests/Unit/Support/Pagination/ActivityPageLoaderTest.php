<?php

declare(strict_types=1);

use Dotswan\EagleEyeFilament\Support\Pagination\ActivityPage;
use Dotswan\EagleEye\Contracts\Repository\ActivityRepositoryInterface;
use Dotswan\EagleEye\Contracts\Resolution\ActivityEntryPresenterInterface;
use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Data\Query\Cursor;
use Dotswan\EagleEye\Data\Query\QueryResult;
use Dotswan\EagleEye\Enums\SortOrder;
use Dotswan\EagleEyeFilament\Support\Pagination\ActivityPageLoader;
use Dotswan\EagleEyeFilament\Support\Pagination\CursorHistory;
use Dotswan\EagleEyeFilament\Tests\Support\EntryFactory;
use Dotswan\EagleEyeFilament\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(TestCase::class);

it('loads and presents one page while recording the next cursor', function (): void {
    $entry = EntryFactory::make('01J0000000000000000000A1', str_repeat('a', 32));
    $next = new Cursor(new DateTimeImmutable('2026-01-01 00:00:00', new DateTimeZone('UTC')), $entry->id, SortOrder::Descending);
    $repository = Mockery::mock(ActivityRepositoryInterface::class);
    $repository->shouldReceive('search')->once()->andReturn(new QueryResult([$entry], $next, true, 42, true));
    $presenter = Mockery::mock(ActivityEntryPresenterInterface::class);
    $presenter->shouldReceive('present')->once()->with([$entry])->andReturn([$entry]);
    app()->instance(ActivityRepositoryInterface::class, $repository);
    app()->instance(ActivityEntryPresenterInterface::class, $presenter);
    $history = new CursorHistory('sig', [1 => null]);

    $page = app(ActivityPageLoader::class)->load(new ActivityQuery(), $history, 1, 25);

    expect($page->entries)->toBe([$entry])
        ->and($page->page)->toBe(1)
        ->and($page->hasPrevious)->toBeFalse()
        ->and($page->hasNext)->toBeTrue()
        ->and($page->count)->toBe(42)
        ->and($history->cursors())->toBe([1 => null, 2 => $next->encode()]);
});

it('clears the count and forward history when the stream is exhausted', function (): void {
    $entry = EntryFactory::make('01J0000000000000000000A2', str_repeat('b', 32));
    $repository = Mockery::mock(ActivityRepositoryInterface::class);
    $repository->shouldReceive('search')->once()->andReturn(new QueryResult([$entry], null, false, null, false));
    $presenter = Mockery::mock(ActivityEntryPresenterInterface::class);
    $presenter->shouldReceive('present')->once()->andReturn([$entry]);
    app()->instance(ActivityRepositoryInterface::class, $repository);
    app()->instance(ActivityEntryPresenterInterface::class, $presenter);
    $history = new CursorHistory('sig', [1 => null]);

    $page = app(ActivityPageLoader::class)->load(new ActivityQuery(), $history, 1, 25);

    expect($page->hasNext)->toBeFalse()
        ->and($page->count)->toBeNull()
        ->and($history->cursors())->toBe([1 => null]);
});

it('rejects unknown pages and invalid cursors before querying', function (): void {
    $repository = Mockery::mock(ActivityRepositoryInterface::class);
    $repository->shouldNotReceive('search');
    $presenter = Mockery::mock(ActivityEntryPresenterInterface::class);
    $presenter->shouldNotReceive('present');
    app()->instance(ActivityRepositoryInterface::class, $repository);
    app()->instance(ActivityEntryPresenterInterface::class, $presenter);
    $loader = app(ActivityPageLoader::class);

    expect(fn (): ActivityPage => $loader->load(new ActivityQuery(), new CursorHistory('sig', [1 => null]), 7, 25))
        ->toThrow(HttpException::class)
        ->and(fn (): ActivityPage => $loader->load(new ActivityQuery(), new CursorHistory('sig', [1 => str_repeat('x', 513)]), 1, 25))
        ->toThrow(HttpException::class)
        ->and(fn (): ActivityPage => $loader->load(new ActivityQuery(), new CursorHistory('sig', [1 => 'not-a-valid-cursor']), 1, 25))
        ->toThrow(HttpException::class);
});
