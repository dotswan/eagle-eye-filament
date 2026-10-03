<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use Dotswan\EagleEye\Data\ActivityDraft;
use Dotswan\EagleEye\Data\ActivityEntry;
use Dotswan\EagleEye\Data\TraceContext;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Enums\ActivitySeverity;

final class EntryFactory
{
    public static function make(
        string $id,
        string $traceId,
        ?DateTimeImmutable $loggedAt = null,
        ?string $causationId = null,
        ActivityChannel $channel = ActivityChannel::Custom,
        ActivitySeverity $severity = ActivitySeverity::Info,
        string $title = 'Activity event',
    ): ActivityEntry {
        $draft = ActivityDraft::make('recorded', $channel, $severity)
            ->withTrace(new TraceContext($traceId, $id, $causationId));
        $draft->id = $id;
        $draft->title = $title;
        $draft->loggedAt = $loggedAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $draft->createdAt = $draft->loggedAt;

        return $draft->freeze();
    }
}
