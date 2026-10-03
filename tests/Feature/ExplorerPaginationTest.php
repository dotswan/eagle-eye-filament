<?php

declare(strict_types=1);

use Dotswan\EagleEye\Facades\EagleEye;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function (): void {
    Gate::define('viewActivityLog', static fn (?object $viewer = null): bool => true);
    Gate::define('revealActivityLog', static fn (?object $viewer = null): bool => true);
});

function seedExplorerEntries(int $count): void
{
    $at = new DateTimeImmutable('-20 minutes', new DateTimeZone('UTC'));

    for ($sequence = 1; $sequence <= $count; $sequence++) {
        companionEntry(
            sprintf('01J%021d', 90_000 + $sequence),
            'Pagination probe '.$sequence,
            loggedAt: $at->modify("+{$sequence} seconds"),
        );
    }
}

it('renders newer and older controls when more entries exist', function (): void {
    EagleEye::fake();
    seedExplorerEntries(60);

    $html = Livewire::test(ListActivityLogs::class)->html();

    expect($html)->toContain('wire:click="nextPage"')
        ->and($html)->toContain('Showing 1–25');
});

it('shows the showing indicator and previous control on the second page', function (): void {
    EagleEye::fake();
    seedExplorerEntries(60);

    Livewire::test(ListActivityLogs::class)
        ->call('nextPage')
        ->assertSee('Showing 26–50')
        ->assertSee('page 2')
        ->assertSee('wire:click="previousPage"', false);
});

it('honours the fifty per page size', function (): void {
    EagleEye::fake();
    seedExplorerEntries(60);

    $component = Livewire::test(ListActivityLogs::class)->call('setPerPage', 50);

    $component->assertSee('Showing 1–50');

    expect(count(explorerInstance($component->instance())->getEntries()))->toBe(50);
});

it('keeps pagination working after the minute rolls over', function (): void {
    EagleEye::fake();
    seedExplorerEntries(60);

    $component = Livewire::test(ListActivityLogs::class);
    $frozen = explorerInstance($component->instance())->windowEndsAt;

    Carbon::setTestNow(Carbon::now()->addMinutes(2));

    $component->call('nextPage')->assertSee('Showing 26–50');

    expect(explorerInstance($component->instance())->windowEndsAt)->toBe($frozen);

    Carbon::setTestNow();
});

it('rejects an unsupported per page size with a 422', function (): void {
    EagleEye::fake();
    seedExplorerEntries(3);

    Livewire::test(ListActivityLogs::class)
        ->set('perPage', 33)
        ->assertStatus(422);
});

it('resets the cursor history when the page size changes', function (): void {
    EagleEye::fake();
    seedExplorerEntries(60);

    $component = Livewire::test(ListActivityLogs::class)->call('nextPage');

    expect(explorerInstance($component->instance())->activityPageCursors)->toHaveKey(2);

    $component->call('setPerPage', 50);

    $instance = explorerInstance($component->instance());

    expect($instance->page)->toBe(1)
        ->and($instance->activityPageCursors)->toHaveKey(1);
});

it('returns to the newest page', function (): void {
    EagleEye::fake();
    seedExplorerEntries(80);

    $component = Livewire::test(ListActivityLogs::class)
        ->call('nextPage')
        ->call('nextPage')
        ->call('nextPage');

    $component->assertSee('Back to newest');
    $component->call('firstPage');

    expect(explorerInstance($component->instance())->page)->toBe(1);
});

it('renders the pager footer markup for the explorer', function (): void {
    EagleEye::fake();
    seedExplorerEntries(60);

    Livewire::test(ListActivityLogs::class)
        ->assertSee('ee-pager', false)
        ->assertSee('Per page', false);
});
