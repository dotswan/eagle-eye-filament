<?php

declare(strict_types=1);

use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Data\RequestSnapshot;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Enums\ActivitySeverity;
use Dotswan\EagleEye\Enums\Capability;
use Dotswan\EagleEye\Facades\EagleEye;
use Dotswan\EagleEyeFilament\Filament\Livewire\ExplorerPane;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

beforeEach(function (): void {
    Gate::define('viewActivityLog', static fn (?object $viewer = null): bool => true);
    Gate::define('revealActivityLog', static fn (?object $viewer = null): bool => true);
});

it('renders the pane origin block and record history link', function (): void {
    EagleEye::fake();
    companionEntry(
        '01J0000000000000000000V1',
        'Request with origin',
        ActivityChannel::Http,
        traceId: str_repeat('e', 32),
        request: new RequestSnapshot('GET', '/orders', 'orders.index', 200, 90, '127.0.0.1', 'curl/8'),
    );

    $pane = Livewire::test(ExplorerPane::class, ['initialId' => '01J0000000000000000000V1']);
    $pane->assertSee('127.0.0.1')
        ->assertSee('curl/8')
        ->assertSee('Origin');

    $pane->call('loadContext')
        ->assertSee('Open record history')
        ->assertSee('activity-subject');
});

it('accepts structured tokens when full text search is unsupported', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000V2', 'Http event', ActivityChannel::Http);
    fakeSupportedCapabilities([Capability::Aggregation, Capability::ExactCount, Capability::UnboundedTimeRange, Capability::RankedAggregation]);
    $spy = spyActivityRepository();

    $component = Livewire::test(ListActivityLogs::class)
        ->set('searchInput', 'channel:http');

    $spy->searches = 0;

    $component->call('submitSearch');

    expect(explorerInstance($component->instance())->getSearchErrors())->toBe([])
        ->and($spy->searches)->toBe(1);

    $component->set('searchInput', 'free text')
        ->call('submitSearch');

    expect(explorerInstance($component->instance())->getSearchErrors())->toBe(['Free-text search is not supported by the active log backend.']);
});

it('applies range presets and rejects an unknown preset with a 422', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000V3', 'Range event');

    $component = Livewire::test(ListActivityLogs::class)->call('setRange', '24h');
    $component->assertOk();
    $component->assertSet('range', '24h');

    Livewire::test(ListActivityLogs::class)->call('setRange', '5y')->assertStatus(422);
});

it('shows an inline error and skips the query for an invalid custom range', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000V4', 'Custom range event');
    $spy = spyActivityRepository();

    $component = Livewire::test(ListActivityLogs::class)
        ->call('openCustomRange')
        ->set('draftFrom', '2020-01-01')
        ->set('draftUntil', '2019-01-01');

    $spy->searches = 0;

    $component->call('applyCustomRange');

    expect(explorerInstance($component->instance())->getFilterError())->not->toBeNull()
        ->and($spy->searches)->toBe(0);
});

it('sets range to custom when a custom date is applied', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000V5', 'Range reset event');

    $component = Livewire::test(ListActivityLogs::class)
        ->call('setRange', '7d')
        ->call('openCustomRange')
        ->set('draftFrom', '2026-09-01')
        ->set('draftUntil', '2026-09-10')
        ->call('applyCustomRange');

    $instance = explorerInstance($component->instance());

    expect($instance->range)->toBe('custom')
        ->and($instance->from)->toBe('2026-09-01')
        ->and($instance->until)->toBe('2026-09-10');
});

it('defaults to a 24 hour window applied to the list and the facets', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000V6', 'Window probe');
    $spy = spyActivityRepository();
    $instance = explorerInstance(Livewire::test(ListActivityLogs::class)->instance());

    expect($instance->range)->toBe('24h');

    $instance->getActivityPage();
    $listQuery = $spy->lastSearchQuery;

    expect($listQuery)->toBeInstanceOf(ActivityQuery::class);

    if (! $listQuery instanceof ActivityQuery) {
        return;
    }

    expect($listQuery->from)->not->toBeNull()
        ->and($listQuery->to)->not->toBeNull();

    $from = $listQuery->from;
    $to = $listQuery->to;

    if (! $from instanceof DateTimeImmutable || ! $to instanceof DateTimeImmutable) {
        return;
    }

    $span = $to->getTimestamp() - $from->getTimestamp();

    expect($span)->toBeGreaterThanOrEqual(86400)->toBeLessThanOrEqual(86401);

    $instance->getChannelFacets();

    expect($spy->lastAggregateQuery?->from)->not->toBeNull();
});

it('keeps the list window unchanged when the custom form is only opened', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000V7', 'Open probe');
    $spy = spyActivityRepository();
    $component = Livewire::test(ListActivityLogs::class)->call('setRange', '7d');
    $instance = explorerInstance($component->instance());
    $instance->getActivityPage();

    $before = $spy->lastSearchQuery;

    $component->call('openCustomRange');

    $instance = explorerInstance($component->instance());
    $instance->getActivityPage();
    $after = $spy->lastSearchQuery;

    expect($instance->range)->toBe('7d')
        ->and(abs($before?->from?->getTimestamp() - $after?->from?->getTimestamp()))->toBeLessThanOrEqual(1);
});

it('labels the active window in the header and the facet rail', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000V8', 'Label probe');

    $component = Livewire::test(ListActivityLogs::class);
    $instance = explorerInstance($component->instance());

    expect($instance->getWindowLabel())->toBe('Last 24 hours')
        ->and($instance->getCountWindowLabel())->toBe('Counts · Last 24 hours')
        ->and($instance->getResultLabel())->toContain('Last 24 hours')
        ->and($component->html())->toContain('Last 24 hours');
});

it('renders explorer rows as links and exposes the filters disclosure', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000V9', 'Responsive probe', ActivityChannel::Http);

    $html = Livewire::test(ListActivityLogs::class)->html();

    expect($html)->toContain('ee-filters')
        ->and($html)->toContain('/activity-logs/01J0000000000000000000V9');
});

it('renders the explorer as three separated cards without aside elements', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000VA', 'Card probe', ActivityChannel::Http);

    $html = Livewire::test(ListActivityLogs::class)->html();

    expect($html)->not->toContain('<aside')
        ->and($html)->toContain('ee-explorer__facets')
        ->and($html)->toContain('ee-explorer__list')
        ->and($html)->toContain('ee-explorer__pane');
});

it('shows a neutral channel chip and only warning-or-worse severity chips', function (): void {
    EagleEye::fake();
    $info = companionEntry('01J0000000000000000000VB', 'Info probe', ActivityChannel::Http, ActivitySeverity::Info);
    $warning = companionEntry('01J0000000000000000000VC', 'Warning probe', ActivityChannel::Http, ActivitySeverity::Warning);

    $infoHtml = Blade::render('<x-eagle-eye-filament::entry-row :entry="$entry" />', ['entry' => $info]);
    $warningHtml = Blade::render('<x-eagle-eye-filament::entry-row :entry="$entry" />', ['entry' => $warning]);

    expect($infoHtml)->toContain('ee-chip--neutral')
        ->and($infoHtml)->not->toContain('ee-chip--amber')
        ->and($infoHtml)->not->toContain('ee-entry__action')
        ->and($warningHtml)->toContain('ee-chip--amber');
});
