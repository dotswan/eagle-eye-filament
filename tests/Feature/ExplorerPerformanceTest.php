<?php

declare(strict_types=1);

use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Facades\EagleEye;
use Dotswan\EagleEyeFilament\Filament\Livewire\ExplorerPane;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use Dotswan\EagleEyeFilament\Support\Explorer\FacetCounter;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function (): void {
    Gate::define('viewActivityLog', static fn (?object $viewer = null): bool => true);
    Gate::define('revealActivityLog', static fn (?object $viewer = null): bool => true);
});

it('switches pane tabs client-side without a server round trip', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000P1', 'Pane alpha');
    $spy = spyActivityRepository();

    $html = Livewire::test(ExplorerPane::class, ['initialId' => '01J0000000000000000000P1'])->html();

    expect($html)->toContain('x-show="tab === \'changes\'"')
        ->and($html)->toContain('x-show="tab === \'payload\'"')
        ->and($html)->toContain("tab = 'payload'")
        ->and($spy->searches)->toBe(0)
        ->and($spy->aggregates)->toBe(0);
});

it('toggles trace groups client-side without parent methods', function (): void {
    $html = Livewire::test(ListActivityLogs::class)->html();

    expect($html)->not->toContain('wire:click="toggleGroup"')
        ->and($html)->not->toContain('wire:click="selectTab"');
});

it('serves facet counts from cache until the filter signature changes', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000P2', 'Facet alpha', ActivityChannel::Http);
    Cache::flush();
    $spy = spyActivityRepository();

    $first = explorerInstance(Livewire::test(ListActivityLogs::class)->instance());
    $first->getChannelFacets();
    $first->getSeverityFacets();
    $first->getActionFacets();
    $afterFirst = $spy->aggregates + $spy->aggregateTops;

    $second = explorerInstance(Livewire::test(ListActivityLogs::class)->instance());
    $second->getChannelFacets();
    $second->getSeverityFacets();
    $second->getActionFacets();

    expect($spy->aggregates + $spy->aggregateTops)->toBe($afterFirst)
        ->and($afterFirst)->toBeGreaterThan(0);

    explorerInstance(Livewire::test(ListActivityLogs::class)->set('channels', ['http'])->instance())->getChannelFacets();

    expect($spy->aggregates + $spy->aggregateTops)->toBeGreaterThan($afterFirst);
});

it('keys the facet cache per viewer', function (): void {
    EagleEye::fake();
    Cache::flush();
    $spy = spyActivityRepository();
    $counter = app(FacetCounter::class);

    $counter->total(new ActivityQuery(), 'signature', new GenericUser(['id' => '1']));
    $counter->total(new ActivityQuery(), 'signature', new GenericUser(['id' => '2']));

    expect($spy->counts)->toBe(2);
});
