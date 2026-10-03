<?php

declare(strict_types=1);

use Dotswan\EagleEyeFilament\Filament\Pages\UserActivityPage;
use Dotswan\EagleEyeFilament\Filament\Pages\SubjectHistoryPage;
use Dotswan\EagleEye\Data\RequestSnapshot;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Enums\ActivitySeverity;
use Dotswan\EagleEye\Facades\EagleEye;
use Dotswan\EagleEyeFilament\Filament\Pages\RecentTracesPage;
use Dotswan\EagleEyeFilament\Filament\Pages\TraceTimelinePage;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function (): void {
    Gate::define('viewActivityLog', static fn (?object $viewer = null): bool => true);
    Gate::define('revealActivityLog', static fn (?object $viewer = null): bool => true);
});

it('exposes the recent traces page at the activity-traces slug without navigation', function (): void {
    expect(RecentTracesPage::getUrl())->toContain('activity-traces')
        ->and(RecentTracesPage::shouldRegisterNavigation())->toBeFalse()
        ->and(TraceTimelinePage::shouldRegisterNavigation())->toBeFalse();
});

it('lays out the waterfall from durations and offsets with markers for non-http', function (): void {
    EagleEye::fake();
    $trace = str_repeat('b', 32);
    $t0 = new DateTimeImmutable('-10 minutes', new DateTimeZone('UTC'));
    $root = companionEntry(
        sprintf('01J%021d', 100_001),
        'Root request',
        ActivityChannel::Http,
        loggedAt: $t0,
        traceId: $trace,
        request: new RequestSnapshot('GET', 'https://example.test/one', 'orders.one', 200, 100, '127.0.0.1'),
    );
    companionEntry(
        sprintf('01J%021d', 100_002),
        'Model child',
        ActivityChannel::Model,
        loggedAt: $t0->modify('+50 milliseconds'),
        traceId: $trace,
        causationId: $root->id,
    );
    companionEntry(
        sprintf('01J%021d', 100_003),
        'Second request',
        ActivityChannel::Http,
        loggedAt: $t0->modify('+150 milliseconds'),
        traceId: $trace,
        request: new RequestSnapshot('GET', 'https://example.test/two', 'orders.two', 200, 50, '127.0.0.1'),
    );

    $component = Livewire::withQueryParams(['trace' => $trace])->test(TraceTimelinePage::class);
    $spans = traceInstance($component->instance())->getSpans();

    expect($spans)->toHaveCount(3)
        ->and($spans[0]['depth'])->toBe(0)
        ->and($spans[1]['depth'])->toBe(1)
        ->and($spans[0]['hasDuration'])->toBeTrue()
        ->and($spans[1]['hasDuration'])->toBeFalse()
        ->and(round($spans[0]['offsetPercent'], 1))->toBe(0.0)
        ->and(round($spans[0]['widthPercent'], 1))->toBe(50.0)
        ->and(round($spans[2]['offsetPercent'], 1))->toBe(75.0)
        ->and(round($spans[2]['widthPercent'], 1))->toBe(25.0)
        ->and($spans[2]['durationLabelLeft'])->toBe(88.0);

    $component->assertSee('Root request')
        ->assertSee('100ms')
        ->assertSee('50ms');
});

it('renders waterfall stats for duration events writes and errors', function (): void {
    EagleEye::fake();
    $trace = str_repeat('c', 32);
    $t0 = new DateTimeImmutable('-10 minutes', new DateTimeZone('UTC'));
    $root = companionEntry(
        sprintf('01J%021d', 110_001),
        'Root request',
        ActivityChannel::Http,
        ActivitySeverity::Info,
        loggedAt: $t0,
        traceId: $trace,
        request: new RequestSnapshot('GET', 'https://example.test', 'orders.show', 200, 250, '127.0.0.1'),
    );
    companionEntry(
        sprintf('01J%021d', 110_002),
        'Failing write',
        ActivityChannel::Model,
        ActivitySeverity::Error,
        loggedAt: $t0->modify('+20 milliseconds'),
        traceId: $trace,
        causationId: $root->id,
    );

    $component = Livewire::withQueryParams(['trace' => $trace])->test(TraceTimelinePage::class);
    $stats = traceInstance($component->instance())->getStats();

    expect($stats['duration'])->toBe('250ms')
        ->and($stats['durationIsSpan'])->toBeFalse()
        ->and($stats['events'])->toBe(2)
        ->and($stats['eventsIncomplete'])->toBeFalse()
        ->and($stats['writes'])->toBe(1)
        ->and($stats['errors'])->toBe(1);

    $component->assertSee('250ms')
        ->assertSee('Writes')
        ->assertSee('Errors');
});

it('limits the selected span to loaded ids', function (): void {
    EagleEye::fake();
    $trace = str_repeat('d', 32);
    $t0 = new DateTimeImmutable('-10 minutes', new DateTimeZone('UTC'));
    companionEntry(sprintf('01J%021d', 120_001), 'Root request', ActivityChannel::Http, loggedAt: $t0, traceId: $trace, request: new RequestSnapshot('GET', 'https://example.test', 'orders.show', 200, 10, '127.0.0.1'));
    companionEntry(sprintf('01J%021d', 120_002), 'Child one', ActivityChannel::Model, loggedAt: $t0->modify('+10 milliseconds'), traceId: $trace);
    companionEntry(sprintf('01J%021d', 120_003), 'Child two', ActivityChannel::Queue, loggedAt: $t0->modify('+20 milliseconds'), traceId: $trace);

    $component = Livewire::withQueryParams(['trace' => $trace])->test(TraceTimelinePage::class);
    $instance = traceInstance($component->instance());
    $ids = array_map(static fn (array $span): string => $span['id'], $instance->getSpans());

    expect($instance->getSelectedId())->toBe($ids[0]);

    $component->set('selectedId', 'not-a-loaded-id');

    expect(traceInstance($component->instance())->getSelectedId())->toBe($ids[0]);

    $component->set('selectedId', $ids[2]);

    expect(traceInstance($component->instance())->getSelectedId())->toBe($ids[2]);
});

it('renders an incomplete trace banner at the fifty event bound', function (): void {
    EagleEye::fake();
    $trace = str_repeat('e', 32);

    for ($sequence = 1; $sequence <= 51; $sequence++) {
        companionEntry(sprintf('01J%021d', 130_000 + $sequence), 'Bound '.$sequence, loggedAt: new DateTimeImmutable(sprintf('-%d seconds', 120 - $sequence), new DateTimeZone('UTC')), traceId: $trace);
    }

    Livewire::withQueryParams(['trace' => $trace])
        ->test(TraceTimelinePage::class)
        ->assertSee('Showing the first 50 events of this trace.')
        ->assertSee('≥');
});

it('lists only http traces and links each row to the timeline', function (): void {
    EagleEye::fake();
    $httpTrace = str_repeat('f', 32);
    companionEntry(
        sprintf('01J%021d', 140_001),
        'HTTP request root',
        ActivityChannel::Http,
        traceId: $httpTrace,
        request: new RequestSnapshot('GET', 'https://example.test', 'orders.index', 200, 30, '127.0.0.1'),
    );
    companionEntry(sprintf('01J%021d', 140_002), 'Model only event', ActivityChannel::Model, traceId: str_repeat('1', 32));

    $component = Livewire::test(RecentTracesPage::class);

    $component->assertSee('HTTP request root')
        ->assertDontSee('Model only event')
        ->assertSee(TraceTimelinePage::getUrl(['trace' => $httpTrace]));
});

it('cursor-pages the recent traces list', function (): void {
    EagleEye::fake();
    $at = new DateTimeImmutable('-30 minutes', new DateTimeZone('UTC'));

    for ($sequence = 1; $sequence <= 25; $sequence++) {
        companionEntry(
            sprintf('01J%021d', 150_000 + $sequence),
            'Paged trace '.$sequence,
            ActivityChannel::Http,
            loggedAt: $at->modify('+'.$sequence.' seconds'),
            traceId: mb_str_pad(dechex($sequence), 32, '0', STR_PAD_LEFT),
            request: new RequestSnapshot('GET', 'https://example.test/'.$sequence, 'orders.'.$sequence, 200, 10, '127.0.0.1'),
        );
    }

    $component = Livewire::test(RecentTracesPage::class);
    $first = tracesInstance($component->instance())->getActivityPage();

    expect($first)->not->toBeNull()
        ->and($first->hasPrevious)->toBeFalse()
        ->and($first->hasNext)->toBeTrue()
        ->and($first->entries)->toHaveCount(20);

    $component->call('nextPage');
    $second = tracesInstance($component->instance())->getActivityPage();

    expect($second)->not->toBeNull()
        ->and($second->hasPrevious)->toBeTrue()
        ->and($second->hasNext)->toBeFalse()
        ->and($second->entries)->toHaveCount(5);
});

it('counts trace events with a single aggregate per page', function (): void {
    EagleEye::fake();
    $trace = str_repeat('2', 32);
    $t0 = new DateTimeImmutable('-10 minutes', new DateTimeZone('UTC'));
    $root = companionEntry(
        sprintf('01J%021d', 160_001),
        'Request root',
        ActivityChannel::Http,
        loggedAt: $t0,
        traceId: $trace,
        request: new RequestSnapshot('GET', 'https://example.test', 'orders.show', 200, 10, '127.0.0.1'),
    );
    companionEntry(sprintf('01J%021d', 160_002), 'Child one', ActivityChannel::Model, loggedAt: $t0->modify('+1 second'), traceId: $trace, causationId: $root->id);
    companionEntry(sprintf('01J%021d', 160_003), 'Child two', ActivityChannel::Queue, loggedAt: $t0->modify('+2 seconds'), traceId: $trace, causationId: $root->id);

    $spy = spyActivityRepository();
    $component = Livewire::test(RecentTracesPage::class);
    $rows = tracesInstance($component->instance())->getRows();

    expect($spy->aggregates)->toBe(1)
        ->and($rows)->toHaveCount(1)
        ->and($rows[0]['events'])->toBe(3);

    $component->assertSee('Events (±10 min)');
});

it('shows a dash when the trace event aggregate is rejected', function (): void {
    EagleEye::fake();
    companionEntry(
        sprintf('01J%021d', 170_001),
        'Request root',
        ActivityChannel::Http,
        request: new RequestSnapshot('GET', 'https://example.test', 'orders.show', 200, 10, '127.0.0.1'),
    );
    $spy = spyActivityRepository();
    $spy->failAggregatesWithLength = true;

    $component = Livewire::test(RecentTracesPage::class);
    $rows = tracesInstance($component->instance())->getRows();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['events'])->toBeNull()
        ->and($spy->aggregates)->toBe(1);

    $component->assertSee('Events (±10 min)');
});

it('escapes attacker controlled trace titles', function (): void {
    EagleEye::fake();
    companionEntry(
        sprintf('01J%021d', 180_001),
        '<script>alert(1)</script>',
        ActivityChannel::Http,
        request: new RequestSnapshot('GET', 'https://example.test', 'orders.show', 200, 10, '127.0.0.1'),
    );

    $component = Livewire::test(RecentTracesPage::class);

    $component->assertSee('<script>alert(1)</script>')
        ->assertDontSee('<script>', false);

    Livewire::withQueryParams(['trace' => str_repeat('a', 32)])
        ->test(TraceTimelinePage::class)
        ->assertDontSee('<script>', false);
});

it('exposes readable navigation labels for every companion page', function (): void {
    expect(RecentTracesPage::getNavigationLabel())->toBe('Recent traces')
        ->and(TraceTimelinePage::getNavigationLabel())->toBe('Trace timeline')
        ->and(UserActivityPage::getNavigationLabel())->toBe('Actor activity')
        ->and(SubjectHistoryPage::getNavigationLabel())->toBe('Record history');
});

it('does not render the internal title source on the trace page', function (): void {
    EagleEye::fake();
    $trace = str_repeat('7', 32);
    companionEntry(
        '01J0000000000000000000TS',
        'Root request',
        ActivityChannel::Http,
        payload: ['title_source' => ['key' => 'http.request', 'params' => ['secret_marker' => 'leak-me']]],
        traceId: $trace,
        action: 'http.request',
    );

    Livewire::withQueryParams(['trace' => $trace])
        ->test(TraceTimelinePage::class)
        ->assertOk()
        ->assertDontSee('leak-me')
        ->assertDontSee('title_source');
});
