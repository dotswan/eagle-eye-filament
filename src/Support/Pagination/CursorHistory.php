<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Support\Pagination;

final class CursorHistory
{
    public const int MAX_ENTRIES = 32;

    /** @param array<int, ?string> $cursors */
    public function __construct(
        private readonly string $signature = '',
        private array $cursors = [1 => null],
    ) {
        $this->cursors = $this->cap($this->normalize($this->cursors));
    }

    /** @param array<int, ?string> $cursors */
    public static function forSignature(string $signature, string $currentSignature, array $cursors): self
    {
        return $signature === $currentSignature
            ? new self($signature, $cursors)
            : new self($signature, [1 => null]);
    }

    public function signature(): string
    {
        return $this->signature;
    }

    /** @return array<int, ?string> */
    public function cursors(): array
    {
        return $this->cursors;
    }

    public function cursorFor(int $page): ?string
    {
        abort_unless(array_key_exists($page, $this->cursors), 422, 'The requested activity page has no known cursor.');

        return $this->cursors[$page];
    }

    public function remember(int $page, ?string $nextCursor): void
    {
        if (null !== $nextCursor) {
            $this->cursors[$page + 1] = $nextCursor;
        } else {
            $this->truncateAfter($page);
        }

        $this->cursors = $this->cap($this->cursors);
    }

    public function truncateAfter(int $page): void
    {
        $this->cursors = array_filter(
            $this->cursors,
            static fn (int $knownPage): bool => $knownPage <= $page,
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function earliestPage(): int
    {
        $pages = array_keys($this->cursors);

        return [] === $pages ? 1 : min($pages);
    }

    /**
     * @param  array<int, ?string>  $cursors
     * @return array<int, ?string>
     */
    private function normalize(array $cursors): array
    {
        $normalized = [];

        foreach ($cursors as $page => $cursor) {
            $normalized[$page] = is_string($cursor) ? $cursor : null;
        }

        if ([] === $normalized) {
            $normalized[1] = null;
        }

        return $normalized;
    }

    /**
     * @param  array<int, ?string>  $cursors
     * @return array<int, ?string>
     */
    private function cap(array $cursors): array
    {
        if (count($cursors) <= self::MAX_ENTRIES) {
            return $cursors;
        }

        ksort($cursors);

        /** @var array<int, ?string> $sliced */
        $sliced = array_slice($cursors, -self::MAX_ENTRIES, null, true);

        return $sliced;
    }
}
