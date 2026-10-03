<?php

declare(strict_types=1);

use Dotswan\EagleEyeFilament\Support\Pagination\CursorHistory;
use Dotswan\EagleEyeFilament\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(TestCase::class);

it('resets the cursor map when the signature changes', function (): void {
    $reset = CursorHistory::forSignature('new', 'old', [1 => null, 2 => 'cursor']);
    $kept = CursorHistory::forSignature('same', 'same', [1 => null, 2 => 'cursor']);

    expect($reset->cursors())->toBe([1 => null])
        ->and($kept->cursors())->toBe([1 => null, 2 => 'cursor']);
});

it('returns the cursor for a known page and rejects unknown jumps', function (): void {
    $history = new CursorHistory('sig', [1 => null, 2 => 'cursor']);

    expect($history->cursorFor(1))->toBeNull()
        ->and($history->cursorFor(2))->toBe('cursor')
        ->and(fn (): ?string => $history->cursorFor(7))->toThrow(HttpException::class);
});

it('remembers forward cursors and truncates when the stream ends', function (): void {
    $history = new CursorHistory('sig', [1 => null, 2 => 'a', 3 => 'b']);

    $history->remember(1, 'next');

    expect($history->cursors())->toBe([1 => null, 2 => 'next', 3 => 'b']);

    $history->remember(2, null);

    expect($history->cursors())->toBe([1 => null, 2 => 'next']);
});

it('caps the retained history at the most recent entries', function (): void {
    $history = new CursorHistory('sig');

    for ($page = 1; $page <= 40; $page++) {
        $history->remember($page, 'cursor-'.$page);
    }

    expect($history->cursors())->toHaveCount(CursorHistory::MAX_ENTRIES)
        ->and($history->cursors())->toHaveKey(41)
        ->and($history->cursors())->not->toHaveKey(1)
        ->and($history->earliestPage())->toBe(10);
});

it('drops forward cursors after a page', function (): void {
    $history = new CursorHistory('sig', [1 => null, 2 => 'a', 3 => 'b']);

    $history->truncateAfter(1);

    expect($history->cursors())->toBe([1 => null]);
});

it('reports the earliest retained page', function (): void {
    $history = new CursorHistory('sig', [5 => null, 6 => 'a']);

    expect($history->earliestPage())->toBe(5);
});
