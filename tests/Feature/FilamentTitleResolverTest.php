<?php

declare(strict_types=1);

use Dotswan\EagleEye\Data\ActivityDraft;
use Dotswan\EagleEye\Data\RequestSnapshot;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEyeFilament\Support\Title\FilamentHttpTitleResolver;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    FilamentHttpTitleResolver::resetRouteMap();
});

it('labels filament page routes at capture time', function (): void {
    $draft = ActivityDraft::make('http.request', ActivityChannel::Http);
    $draft->request = new RequestSnapshot('GET', '/test-settings', 'filament.testing.pages.test-settings', 200);

    $title = app(FilamentHttpTitleResolver::class)->resolve($draft);

    expect($title)->toBe('Viewed Settings')
        ->and($draft->payload['title_source']['key'])->toBe('titles.http.page');
});

it('returns null for non-panel routes', function (): void {
    $draft = ActivityDraft::make('http.request', ActivityChannel::Http);
    $draft->request = new RequestSnapshot('GET', '/orders', 'orders.index', 200);

    expect(app(FilamentHttpTitleResolver::class)->resolve($draft))->toBeNull();
});

it('resolves the route map without querying the database', function (): void {
    DB::connection()->enableQueryLog();

    $draft = ActivityDraft::make('http.request', ActivityChannel::Http);
    $draft->request = new RequestSnapshot('GET', '/test-settings', 'filament.testing.pages.test-settings', 200);
    app(FilamentHttpTitleResolver::class)->resolve($draft);

    expect(DB::connection()->getQueryLog())->toBe([]);
});

it('labels livewire updates from the collected component context', function (): void {
    $draft = ActivityDraft::make('http.request', ActivityChannel::Http);
    $draft->request = new RequestSnapshot('POST', '/livewire-abc123/update', 'default-livewire.update', 200);
    $draft->payload['livewire'] = [
        'components' => [['name' => 'edit-order', 'page' => 'Orders', 'action' => ['name' => 'save', 'label' => 'Save']]],
        'quiet'      => false,
        'truncated'  => false,
    ];

    $title = app(FilamentHttpTitleResolver::class)->resolve($draft);

    expect($title)->toBe('Save on Orders')
        ->and($draft->payload['title_source']['key'])->toBe('titles.http.livewire_action');
});
