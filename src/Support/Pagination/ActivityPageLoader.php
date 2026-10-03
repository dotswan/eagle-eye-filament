<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Support\Pagination;

use Dotswan\EagleEye\Contracts\Repository\ActivityRepositoryInterface;
use Dotswan\EagleEye\Contracts\Resolution\ActivityEntryPresenterInterface;
use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Data\Query\Cursor;
use Throwable;

final readonly class ActivityPageLoader
{
    public const int DEFAULT_PER_PAGE = 25;
    public const int MAX_PER_PAGE = 100;
    public const int MAX_CURSOR_LENGTH = 512;

    public function __construct(
        private ActivityRepositoryInterface $repository,
        private ActivityEntryPresenterInterface $presenter,
    ) {
    }

    public function load(
        ActivityQuery $query,
        CursorHistory $history,
        int $page,
        int $perPage = self::DEFAULT_PER_PAGE,
    ): ActivityPage {
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));
        $page = max(1, $page);

        $query->limit($perPage);
        $query->cursor($this->decodeCursor($history->cursorFor($page)));

        $result = $this->repository->search($query);
        $history->remember($page, $result->nextCursor?->encode());

        return new ActivityPage(
            entries: $this->presenter->present($result->entries),
            page: $page,
            hasPrevious: $page > $history->earliestPage(),
            hasNext: $result->hasMore,
            count: $result->exactCount ? $result->count : null,
            perPage: $perPage,
        );
    }

    private function decodeCursor(?string $cursor): ?Cursor
    {
        if (null === $cursor) {
            return null;
        }

        if (mb_strlen($cursor, '8bit') > self::MAX_CURSOR_LENGTH) {
            abort(422, 'The activity cursor is invalid.');
        }

        try {
            return Cursor::decode($cursor);
        } catch (Throwable) {
            abort(422, 'The activity cursor is invalid.');
        }
    }
}
