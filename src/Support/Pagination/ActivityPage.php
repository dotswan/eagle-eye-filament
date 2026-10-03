<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Support\Pagination;

use Dotswan\EagleEye\Data\ActivityEntry;

final readonly class ActivityPage
{
    /**
     * @param list<ActivityEntry> $entries
     */
    public function __construct(
        public array $entries,
        public int $page,
        public bool $hasPrevious,
        public bool $hasNext,
        public ?int $count = null,
        public int $perPage = 25,
    ) {
    }
}
