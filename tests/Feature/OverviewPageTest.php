<?php

declare(strict_types=1);

use Dotswan\EagleEye\Data\RequestSnapshot;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Enums\ActivitySeverity;
use Dotswan\EagleEye\Enums\Capability;
use Dotswan\EagleEye\Facades\EagleEye;
use Dotswan\EagleEyeFilament\Filament\Pages\ActivityOverviewPage;
use Dotswan\EagleEyeFilament\Filament\Widgets\Overview\OverviewBusiestActors;
use Dotswan\EagleEyeFilament\Filament\Widgets\Overview\OverviewFailures;
use Dotswan\EagleEyeFilament\Filament\Widgets\Overview\OverviewLatestRequests;
use Dotswan\EagleEyeFilament\Filament\Widgets\Overview\OverviewTraffic;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function (): void {
    Gate::define('viewActivityLog', static fn (?object $viewer = null): bool => true);
    Gate::define('revealActivityLog', static fn (?object $viewer = null): bool => true);
});

it('rejects an invalid range with a 422 response', function (): void {
    EagleEye::fake();

    Livewire::test(ActivityOverviewPage::class)->set('range', '5y')->assertStatus(422);
    Livewire::test(ActivityOverviewPage::class, ['range' => '5y'])->assertStatus(422);
});

it('renders the overview shell with the range switch and traffic header', function (): void {
    EagleEye::fake();

    Livewire::test(ActivityOverviewPage::class)
        ->assertOk()
        ->assertSee('Overview')
        ->assertSee('24h')
        ->assertSee('events/min');
});

it('computes traffic kpis and deltas from the series reads', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000K1', 'Request one', ActivityChannel::Http);
    companionEntry('01J0000000000000000000K2', 'Request two', ActivityChannel::Http);
    companionEntry('01J0000000000000000000K3', 'Model one', ActivityChannel::Model);
    companionEntry('01J0000000000000000000K4', 'Model error', ActivityChannel::Model, ActivitySeverity::Error);

    $component = Livewire::test(OverviewTraffic::class, ['range' => '24h']);
    $component->assertOk();
    $instance = trafficInstance($component->instance());

    expect($instance->getEventsTotal())->toBe(4)
        ->and($instance->getRequestsTotal())->toBe(2)
        ->and($instance->getWritesTotal())->toBe(2)
        ->and($instance->getErrorRateLabel())->toBe('25%')
        ->and($instance->getEventsDelta())->toBeNull()
        ->and($instance->getPerMinute())->toBeInt()
        ->and($instance->isAvailable())->toBeTrue();
});

it('reports the error rate delta in points with a bad tone when it rises', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000U1', 'Error one', severity: ActivitySeverity::Error);
    companionEntry('01J0000000000000000000U2', 'Info one', severity: ActivitySeverity::Info);

    $component = Livewire::test(OverviewTraffic::class, ['range' => '24h']);
    $kpis = trafficInstance($component->instance())->getKpis();
    $errorKpi = null;

    foreach ($kpis as $kpi) {
        if ('error_rate' === $kpi['key']) {
            $errorKpi = $kpi;
        }
    }

    expect($errorKpi)->toBeArray();

    if (! is_array($errorKpi)) {
        return;
    }

    expect($errorKpi['delta'])->toBeString()
        ->and($errorKpi['delta'])->toEndWith('pt')
        ->and($errorKpi['deltaTone'])->toBe('bad');
});

it('guards zero division when there is no activity', function (): void {
    EagleEye::fake();

    $component = Livewire::test(OverviewTraffic::class, ['range' => '24h']);
    $component->assertOk()->assertSee('Error rate');
    $instance = trafficInstance($component->instance());

    expect($instance->getEventsTotal())->toBe(0)
        ->and($instance->getErrorRateLabel())->toBe('—')
        ->and($instance->getEventsDelta())->toBeNull()
        ->and($instance->isAvailable())->toBeTrue();
});

it('links every severity mix row to the explorer deep link', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000M1', 'Severity error', severity: ActivitySeverity::Error);
    companionEntry('01J0000000000000000000M2', 'Severity info', severity: ActivitySeverity::Info);

    $component = Livewire::test(OverviewTraffic::class, ['range' => '24h']);
    $rows = trafficInstance($component->instance())->getSeverityMix();

    expect($rows)->not->toBeEmpty();

    $values = array_column($rows, 'value');

    expect($values)->toContain('error', 'info');

    $errorRow = collect($rows)->firstWhere('value', 'error');

    expect($errorRow)->toBeArray();

    $url = is_array($errorRow) ? $errorRow['url'] : '';

    expect($url)->toContain('severity');
});

it('shows a failures alert with distinct trace count and hides it at zero', function (): void {
    EagleEye::fake();
    $base = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    companionEntry('01J0000000000000000000Q1', 'Failure one', severity: ActivitySeverity::Error, loggedAt: $base->modify('-3 minutes'));
    companionEntry('01J0000000000000000000Q2', 'Failure two', severity: ActivitySeverity::Error, loggedAt: $base->modify('-2 minutes'));
    companionEntry('01J0000000000000000000Q3', 'Failure three', severity: ActivitySeverity::Critical, loggedAt: $base->modify('-1 minute'), traceId: str_repeat('b', 32));

    Livewire::test(OverviewFailures::class, ['range' => '24h'])
        ->assertOk()
        ->assertSee('2 traces failed in the last hour')
        ->assertSee('Failure three');

    EagleEye::fake();
    Livewire::test(OverviewFailures::class, ['range' => '24h'])
        ->assertOk()
        ->assertDontSee('traces failed in the last hour');
});

it('lists the latest http requests with trace event counts', function (): void {
    EagleEye::fake();
    companionEntry(
        '01J0000000000000000000R1',
        'Dashboard request',
        ActivityChannel::Http,
        ActivitySeverity::Info,
        request: new RequestSnapshot('GET', '/dashboard', 'dashboard', 200, 120, '127.0.0.1', 'curl'),
    );

    $component = Livewire::test(OverviewLatestRequests::class, ['range' => '24h']);
    $component->assertOk()
        ->assertSee('Latest requests')
        ->assertSee('Open traces →')
        ->assertSee('Dashboard request');

    $rows = latestRequestsInstance($component->instance())->getRows();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['statusTone'])->toBe('green')
        ->and($rows[0]['durationLabel'])->toBe('120ms')
        ->and($rows[0]['events'])->toBe(1);
});

it('ranks the busiest actors and links them to the actor page', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000S1', 'Actor a one', actorId: 'a');
    companionEntry('01J0000000000000000000S2', 'Actor a error', severity: ActivitySeverity::Error, actorId: 'a');
    companionEntry('01J0000000000000000000S3', 'Actor b one', actorId: 'b');

    $component = Livewire::test(OverviewBusiestActors::class, ['range' => '24h']);
    $component->assertOk()->assertSee('Busiest actors')->assertSee('user #a');

    $rows = busiestActorsInstance($component->instance())->getRows();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['events'])->toBe(2)
        ->and($rows[0]['errors'])->toBe(1)
        ->and($rows[0]['url'])->toContain('actor_type')
        ->and($rows[1]['events'])->toBe(1);
});

it('hides the actor error labels when the ranking cutoff is ambiguous', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000T1', 'Actor a one', actorId: 'a');
    $spy = spyActivityRepository();
    $spy->failAggregatesWithLength = true;

    $component = Livewire::test(OverviewBusiestActors::class, ['range' => '24h']);
    $instance = busiestActorsInstance($component->instance());
    $rows = $instance->getRows();

    expect($instance->errorsAvailable())->toBeFalse()
        ->and($rows[0]['errors'])->toBeNull();
});

it('hides the page when aggregation is unsupported', function (): void {
    EagleEye::fake();
    fakeSupportedCapabilities([Capability::SeriesAggregation, Capability::RankedAggregation]);

    expect(ActivityOverviewPage::canAccess())->toBeFalse();
    Livewire::test(ActivityOverviewPage::class)->assertForbidden();
});

it('renders an explicit unsupported state without series aggregation', function (): void {
    EagleEye::fake();
    fakeSupportedCapabilities([
        Capability::Aggregation,
        Capability::RankedAggregation,
        Capability::FullTextSearch,
        Capability::ExactCount,
        Capability::UnboundedTimeRange,
    ]);

    $component = Livewire::test(OverviewTraffic::class, ['range' => '24h']);
    $component->assertOk()->assertSee('Not supported by the active log backend');

    expect(trafficInstance($component->instance())->isSupported())->toBeFalse();
});
