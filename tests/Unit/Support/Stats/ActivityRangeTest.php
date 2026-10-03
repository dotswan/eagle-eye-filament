<?php

declare(strict_types=1);

use Dotswan\EagleEyeFilament\Support\Stats\ActivityRange;

it('maps ranges to durations steps and bucket counts', function (): void {
    expect(ActivityRange::Hour->durationSeconds())->toBe(3600)
        ->and(ActivityRange::Hour->stepSeconds())->toBe(300)
        ->and(ActivityRange::Hour->bucketCount())->toBe(12)
        ->and(ActivityRange::Day->stepSeconds())->toBe(3600)
        ->and(ActivityRange::Day->bucketCount())->toBe(24)
        ->and(ActivityRange::Week->stepSeconds())->toBe(21600)
        ->and(ActivityRange::Week->bucketCount())->toBe(28)
        ->and(ActivityRange::Month->stepSeconds())->toBe(86400)
        ->and(ActivityRange::Month->bucketCount())->toBe(30)
        ->and(ActivityRange::fromLabel('5y'))->toBeNull()
        ->and(ActivityRange::fromLabel('24h'))->toBe(ActivityRange::Day);
});

it('computes the current and previous windows with axis formats', function (): void {
    $now = new DateTimeImmutable('2026-01-08 00:00:00', new DateTimeZone('UTC'));

    [$from, $to] = ActivityRange::Week->window($now);
    [$previousFrom, $previousTo] = ActivityRange::Week->previous($now);

    expect($to->format('Y-m-d H:i:s'))->toBe('2026-01-08 00:00:00')
        ->and($from->format('Y-m-d H:i:s'))->toBe('2026-01-01 00:00:00')
        ->and($previousTo->format('Y-m-d H:i:s'))->toBe('2026-01-01 00:00:00')
        ->and($previousFrom->format('Y-m-d H:i:s'))->toBe('2025-12-25 00:00:00')
        ->and(ActivityRange::Hour->axisFormat())->toBe('H:i')
        ->and(ActivityRange::Month->axisFormat())->toBe('D d');
});
