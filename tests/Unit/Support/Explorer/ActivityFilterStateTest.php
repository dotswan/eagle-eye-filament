<?php

declare(strict_types=1);

use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Enums\ActivitySeverity;
use Dotswan\EagleEyeFilament\Support\Explorer\ActivityFilterState;
use Dotswan\EagleEyeFilament\Support\Explorer\InvalidActivityFilter;
use Dotswan\EagleEyeFilament\Tests\TestCase;
use PHPUnit\Framework\Assert;

uses(TestCase::class);

it('builds a validated state and applies it to the query', function (): void {
    $trace = str_repeat('a', 32);
    $state = ActivityFilterState::fromInput([
        'channels'    => ['http', 'model'],
        'severities'  => ['error'],
        'actions'     => ['model.updated'],
        'actorType'   => 'user',
        'actorId'     => '7',
        'subjectType' => 'order',
        'subjectId'   => '1',
        'trace'       => $trace,
        'search'      => '  hello  ',
        'from'        => '2026-01-01',
        'until'       => '2026-01-02',
    ]);

    $query = $state->applyTo(new ActivityQuery());

    expect($query->channels)->toBe([ActivityChannel::Http, ActivityChannel::Model])
        ->and($query->severities)->toBe([ActivitySeverity::Error])
        ->and($query->actions)->toBe(['model.updated'])
        ->and($query->actorType)->toBe('user')
        ->and($query->actorId)->toBe('7')
        ->and($query->subjectType)->toBe('order')
        ->and($query->subjectId)->toBe('1')
        ->and($query->traceId)->toBe($trace)
        ->and($query->search)->toBe('hello')
        ->and($query->from)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($query->to)->toBeInstanceOf(DateTimeImmutable::class);
});

it('keeps actor and subject types when no id is supplied', function (): void {
    $query = ActivityFilterState::fromInput([
        'actorType'   => 'system',
        'subjectType' => 'order',
    ])->applyTo(new ActivityQuery());

    expect($query->actorType)->toBe('system')
        ->and($query->actorId)->toBeNull()
        ->and($query->subjectType)->toBe('order')
        ->and($query->subjectId)->toBeNull();
});

it('rejects unknown channel and severity values with the offending field', function (): void {
    try {
        ActivityFilterState::fromInput(['channels' => ['nope']]);
        Assert::fail('Expected an invalid channel filter.');
    } catch (InvalidActivityFilter $exception) {
        expect($exception->field)->toBe('channels');
    }

    try {
        ActivityFilterState::fromInput(['severities' => ['nope']]);
        Assert::fail('Expected an invalid severity filter.');
    } catch (InvalidActivityFilter $exception) {
        expect($exception->field)->toBe('severities');
    }
});

it('rejects oversized and control-character identifiers', function (): void {
    expect(fn (): ActivityFilterState => ActivityFilterState::fromInput(['actorType' => str_repeat('a', 257)]))
        ->toThrow(InvalidActivityFilter::class)
        ->and(fn (): ActivityFilterState => ActivityFilterState::fromInput(['subjectType' => "bad\n"]))
        ->toThrow(InvalidActivityFilter::class);
});

it('rejects invalid traces and excessive action filters', function (): void {
    expect(fn (): ActivityFilterState => ActivityFilterState::fromInput(['trace' => 'not-a-trace']))
        ->toThrow(InvalidActivityFilter::class)
        ->and(fn (): ActivityFilterState => ActivityFilterState::fromInput(['trace' => str_repeat('0', 32)]))
        ->toThrow(InvalidActivityFilter::class)
        ->and(fn (): ActivityFilterState => ActivityFilterState::fromInput(['actions' => array_fill(0, 21, 'action.one')]))
        ->toThrow(InvalidActivityFilter::class)
        ->and(fn (): ActivityFilterState => ActivityFilterState::fromInput(['actions' => ['bad value']]))
        ->toThrow(InvalidActivityFilter::class);
});

it('rejects invalid dates and ranges before any query runs', function (array $input): void {
    expect(fn (): ActivityFilterState => ActivityFilterState::fromInput($input))->toThrow(InvalidActivityFilter::class);
})->with([
    'array date'       => [['from' => ['2026-01-01'], 'until' => '2026-01-02']],
    'invalid calendar' => [['from' => '2026-02-30', 'until' => '2026-03-01']],
    'reversed range'   => [['from' => '2026-03-02', 'until' => '2026-03-01']],
    'excessive range'  => [['from' => '2020-01-01', 'until' => '2026-01-01']],
]);

it('derives the remaining window when only one date bound is supplied', function (): void {
    $state = ActivityFilterState::fromInput(['until' => '2026-01-02']);

    expect($state->from)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($state->until)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($state->until?->format('Y-m-d H:i:s'))->toBe('2026-01-03 00:00:00');
});

it('produces a stable signature that changes with the filters', function (): void {
    $first = ActivityFilterState::fromInput(['channels' => ['http']]);
    $same = ActivityFilterState::fromInput(['channels' => ['http']]);
    $other = ActivityFilterState::fromInput(['channels' => ['model']]);

    expect($first->signature())->toBe($same->signature())
        ->and($first->signature())->not->toBe($other->signature());
});
