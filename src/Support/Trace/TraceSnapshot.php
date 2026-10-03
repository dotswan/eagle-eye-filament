<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Support\Trace;

use DateTimeImmutable;
use Dotswan\EagleEye\Data\ActivityEntry;
use Dotswan\EagleEye\Data\TraceTree;

final readonly class TraceSnapshot
{
    /**
     * @param list<ActivityEntry> $entries
     * @param list<TraceTree>     $roots
     */
    public function __construct(
        public string $traceId,
        public array $entries,
        public array $roots,
        public bool $incomplete,
        public ?DateTimeImmutable $t0,
    ) {
    }
}
