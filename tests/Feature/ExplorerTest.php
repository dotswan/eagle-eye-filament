<?php

declare(strict_types=1);

use Dotswan\EagleEye\Data\ActivityEntry;
use Dotswan\EagleEye\Data\SecretRef;
use Dotswan\EagleEye\Drivers\DriverManager;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Enums\ActivitySeverity;
use Dotswan\EagleEye\Facades\EagleEye;
use Dotswan\EagleEyeFilament\Filament\Livewire\ExplorerPane;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use Dotswan\EagleEyeFilament\Support\Pagination\ActivityPage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;

beforeEach(function (): void {
    Gate::define('viewActivityLog', static fn (?object $viewer = null): bool => true);
    Gate::define('revealActivityLog', static fn (?object $viewer = null): bool => true);
});

it('lists entries from the repository through the explorer', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000A1', 'First activity');
    companionEntry('01J0000000000000000000A2', 'Second activity');

    Livewire::test(ListActivityLogs::class)
        ->assertOk()
        ->assertSee('First activity')
        ->assertSee('Second activity');
});

it('renders the explorer navigation and both variants', function (): void {
    EagleEye::fake();

    Livewire::test(ListActivityLogs::class)
        ->assertSee('Explorer')
        ->assertSee('Stream')
        ->assertSee('By trace');
});

it('narrows results with channel severity and action facets', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000F1', 'HTTP event', ActivityChannel::Http, ActivitySeverity::Info, action: 'http.request');
    companionEntry('01J0000000000000000000F2', 'Model event', ActivityChannel::Model, ActivitySeverity::Error, action: 'model.updated');

    $component = Livewire::test(ListActivityLogs::class)
        ->set('channels', [ActivityChannel::Http->value])
        ->assertSee('HTTP event')
        ->assertDontSee('Model event');

    $component->set('channels', [])->set('severities', [ActivitySeverity::Error->value])
        ->assertSee('Model event')
        ->assertDontSee('HTTP event');

    $component->set('severities', [])->set('actions', ['model.updated'])
        ->assertSee('Model event')
        ->assertDontSee('HTTP event');

    $component->set('actions', [])->call('loadFacets');
    $channelCounts = [];
    $actionCounts = [];

    foreach (explorerInstance($component->instance())->getChannelFacets() as $facet) {
        $channelCounts[$facet['value']] = $facet['count'];
    }

    foreach (explorerInstance($component->instance())->getActionFacets() as $facet) {
        $actionCounts[$facet['value']] = $facet['count'];
    }

    expect($channelCounts[ActivityChannel::Http->value])->toBe(1)
        ->and($channelCounts[ActivityChannel::Model->value])->toBe(1)
        ->and($actionCounts['http.request'])->toBe(1)
        ->and($actionCounts['model.updated'])->toBe(1);
});

it('keeps the list when facet counts fail', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000G1', 'Count failure survivor');
    $spy = spyActivityRepository();
    $spy->failAggregates = true;

    Livewire::test(ListActivityLogs::class)
        ->call('loadFacets')
        ->assertOk()
        ->assertSee('Count failure survivor')
        ->assertSee('counts unavailable');
});

it('runs exactly one search when submitting a token search', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000S1', 'Searchable activity', ActivityChannel::Http, action: 'model.updated');
    $spy = spyActivityRepository();

    $component = Livewire::test(ListActivityLogs::class);
    $component->set('searchInput', 'action:model.updated channel:http');
    $spy->searches = 0;
    $component->call('submitSearch')->assertSee('Searchable activity');

    expect($spy->searches)->toBe(1)
        ->and(explorerInstance($component->instance())->channels)->toBe([ActivityChannel::Http->value])
        ->and(explorerInstance($component->instance())->actions)->toBe(['model.updated']);
});

it('runs zero repository calls for an invalid search token', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000S2', 'Ignored activity');
    $spy = spyActivityRepository();

    $component = Livewire::test(ListActivityLogs::class);
    $component->set('searchInput', 'severity:erorr');
    $spy->searches = 0;
    $spy->aggregates = 0;
    $component->call('submitSearch')->assertSee('Unknown activity severity');

    expect($spy->searches)->toBe(0)
        ->and($spy->aggregates)->toBe(0);
});

it('rejects tampered channel filters', function (): void {
    EagleEye::fake();

    Livewire::withQueryParams(['channel' => ['bogus']])
        ->test(ListActivityLogs::class)
        ->assertStatus(422);
});

it('rejects oversized action filters', function (): void {
    EagleEye::fake();

    Livewire::test(ListActivityLogs::class)
        ->set('actions', array_fill(0, 21, 'action'))
        ->assertStatus(422);
});

it('rejects a tampered pane selection with a 422', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000T1', 'Selection probe');
    $spy = spyActivityRepository();

    Livewire::test(ExplorerPane::class, ['initialId' => null])
        ->set('selectedId', "bad\n")
        ->assertStatus(422);

    expect($spy->finds)->toBe(0);
});

it('falls back when a pane selection no longer exists', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000T2', 'Existing activity');
    $spy = spyActivityRepository();

    $component = Livewire::test(ExplorerPane::class, ['initialId' => '01J0000000000000000000T2'])
        ->set('selectedId', '01J0000000000000000000ZZ');
    $component->assertOk();

    expect($spy->finds)->toBeGreaterThanOrEqual(1)
        ->and(explorerPaneInstance($component->instance())->getSelectedEntry()?->id)->toBe('01J0000000000000000000T2');
});

it('shows the selected entry and lazily loads its context', function (): void {
    EagleEye::fake();
    $trace = str_repeat('c', 32);
    companionEntry('01J0000000000000000000C1', 'Selected event', ActivityChannel::Http, loggedAt: new DateTimeImmutable('-2 minutes', new DateTimeZone('UTC')), traceId: $trace);
    companionEntry('01J0000000000000000000C2', 'Sibling event', ActivityChannel::Model, loggedAt: new DateTimeImmutable('-1 minute', new DateTimeZone('UTC')), traceId: $trace);

    Livewire::test(ExplorerPane::class, ['initialId' => '01J0000000000000000000C1'])
        ->assertSee('Selected event')
        ->call('loadContext')
        ->assertSee('Same trace')
        ->assertSee('Sibling event')
        ->assertSee('History of this subject');
});

it('groups the current page by trace with offsets and a partial hint', function (): void {
    EagleEye::fake();
    $at = new DateTimeImmutable('-20 minutes', new DateTimeZone('UTC'));

    for ($sequence = 1; $sequence <= 30; $sequence++) {
        companionEntry(
            sprintf('01J%021d', 60_000 + $sequence),
            'Trace group '.$sequence,
            ActivityChannel::Http,
            loggedAt: $at->modify("+{$sequence} seconds"),
            traceId: mb_str_pad(dechex($sequence), 32, '0', STR_PAD_LEFT),
        );
    }

    $component = Livewire::test(ListActivityLogs::class)->set('variant', 'trace');

    $component->assertSee('Open timeline →')
        ->assertSee('May continue on other pages');

    $groups = explorerInstance($component->instance())->getTraceGroups();

    expect($groups)->toHaveCount(25)
        ->and($groups[24]['partial'])->toBeTrue()
        ->and($groups[10]['partial'])->toBeFalse();
});

it('groups children with offsets from the earliest trace event', function (): void {
    EagleEye::fake();
    $at = new DateTimeImmutable('-10 minutes', new DateTimeZone('UTC'));
    $trace = str_repeat('f', 32);
    companionEntry('01J0000000000000000000Z1', 'Root request', ActivityChannel::Http, loggedAt: $at, traceId: $trace);
    companionEntry('01J0000000000000000000Z2', 'Model write', ActivityChannel::Model, loggedAt: $at->modify('+250 milliseconds'), traceId: $trace);

    $component = Livewire::test(ListActivityLogs::class)->set('variant', 'trace');
    $groups = explorerInstance($component->instance())->getTraceGroups();

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['header']->title)->toBe('Root request')
        ->and($groups[0]['entries'])->toHaveCount(2)
        ->and($groups[0]['entries'][1]['offset'])->toBe('+250ms');

    $component->assertSee('+250ms');
});

it('hides the partial hint when the page has no boundary crossers', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000H1', 'Single trace', traceId: str_repeat('d', 32));

    Livewire::test(ListActivityLogs::class)
        ->set('variant', 'trace')
        ->assertDontSee('May continue on other pages');
});

it('never renders sealed values in the pane', function (): void {
    EagleEye::fake();
    companionEntry(
        '01J0000000000000000000K1',
        'Sealed activity',
        secrets: ['token' => new SecretRef('aes-256-gcm', 'fingerprint', 'ciphertext-value')],
    );

    Livewire::test(ExplorerPane::class, ['initialId' => '01J0000000000000000000K1'])
        ->assertSee('sealed values: open full page to reveal')
        ->assertDontSee('ciphertext-value');
});

it('escapes attacker-influenced titles', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000X1', '<script>alert(1)</script>');

    Livewire::test(ListActivityLogs::class)
        ->assertSee('<script>alert(1)</script>')
        ->assertDontSee('<script>', false);
});

it('rejects invalid editable date filters before querying', function (array $params): void {
    EagleEye::fake();
    $spy = spyActivityRepository();

    $component = Livewire::withQueryParams($params)->test(ListActivityLogs::class);

    $component->assertOk();

    expect($spy->searches)->toBe(0)
        ->and(explorerInstance($component->instance())->getFilterError())->toBeString();
})->with([
    'invalid calendar' => [['from' => '2026-02-30', 'until' => '2026-03-01']],
    'reversed range'   => [['from' => '2026-03-02', 'until' => '2026-03-01']],
    'excessive range'  => [['from' => '2020-01-01', 'until' => '2026-01-01']],
]);

it('rejects an unknown page jump', function (): void {
    EagleEye::fake();
    $at = new DateTimeImmutable('-20 minutes', new DateTimeZone('UTC'));

    for ($sequence = 1; $sequence <= 30; $sequence++) {
        companionEntry(sprintf('01J%021d', 70_000 + $sequence), 'Jump probe '.$sequence, loggedAt: $at->modify("+{$sequence} seconds"));
    }

    Livewire::test(ListActivityLogs::class)
        ->set('page', 7)
        ->assertStatus(422);
});

it('resets the cursor history when filters change', function (): void {
    EagleEye::fake();
    $at = new DateTimeImmutable('-20 minutes', new DateTimeZone('UTC'));

    for ($sequence = 1; $sequence <= 30; $sequence++) {
        companionEntry(sprintf('01J%021d', 80_000 + $sequence), 'Reset probe '.$sequence, loggedAt: $at->modify("+{$sequence} seconds"));
    }

    $component = Livewire::test(ListActivityLogs::class)->call('nextPage');

    expect(explorerInstance($component->instance())->activityPageCursors)->toHaveKey(2);

    $component->set('channels', [ActivityChannel::Custom->value]);

    $instance = explorerInstance($component->instance());

    expect($instance->page)->toBe(1)
        ->and($instance->activityPageCursors)->toHaveKey(1);
});

it('pages without repeating records and hides newer at the earliest cursor', function (): void {
    EagleEye::fake();
    $at = new DateTimeImmutable('-20 minutes', new DateTimeZone('UTC'));

    for ($sequence = 1; $sequence <= 30; $sequence++) {
        companionEntry(sprintf('01J%021d', 90_000 + $sequence), 'Paged '.$sequence, loggedAt: $at->modify("+{$sequence} seconds"));
    }

    $component = Livewire::test(ListActivityLogs::class);
    $firstPage = explorerInstance($component->instance())->getActivityPage();

    if (! $firstPage instanceof ActivityPage) {
        throw new LogicException('Explorer page unavailable.');
    }

    $firstIds = array_map(static fn (ActivityEntry $entry): string => $entry->id, $firstPage->entries);

    $component->assertSee('Paged 30')
        ->call('nextPage');

    $secondPage = explorerInstance($component->instance())->getActivityPage();

    if (! $secondPage instanceof ActivityPage) {
        throw new LogicException('Explorer page unavailable.');
    }

    $secondIds = array_map(static fn (ActivityEntry $entry): string => $entry->id, $secondPage->entries);

    expect($firstPage->hasPrevious)->toBeFalse()
        ->and($firstPage->hasNext)->toBeTrue()
        ->and(array_intersect($firstIds, $secondIds))->toBe([])
        ->and($secondPage->hasPrevious)->toBeTrue()
        ->and($secondPage->hasNext)->toBeFalse();
});

it('caps the retained cursor history', function (): void {
    EagleEye::fake(2000);
    $at = new DateTimeImmutable('-60 minutes', new DateTimeZone('UTC'));

    for ($sequence = 1; $sequence <= 830; $sequence++) {
        companionEntry(sprintf('01J%021d', 100_000 + $sequence), 'Cap '.$sequence, loggedAt: $at->modify("+{$sequence} seconds"));
    }

    $component = Livewire::test(ListActivityLogs::class);

    for ($index = 0; $index < 40; $index++) {
        $page = explorerInstance($component->instance())->getActivityPage();

        if (! $page instanceof ActivityPage || ! $page->hasNext) {
            break;
        }

        $component->call('nextPage');
    }

    expect(explorerInstance($component->instance())->activityPageCursors)->toHaveCount(32);
});

it('renders unchanged with the database driver', function (): void {
    useCompanionDatabaseDriver();
    companionEntry('01J0000000000000000000DB', 'Database activity');

    Livewire::test(ListActivityLogs::class)->assertSee('Database activity');
});

it('renders with the Loki driver and skips the exact count', function (): void {
    Http::fake([
        '*' => Http::response(['status' => 'success', 'data' => ['resultType' => 'streams', 'result' => []]]),
    ]);
    config()->set('eagle-eye.default', 'loki');
    config()->set('eagle-eye.drivers.loki.write.path', 'stdout');
    app(DriverManager::class)->forgetDrivers();

    Livewire::test(ListActivityLogs::class)
        ->assertOk()
        ->assertSee('Last 24 hours');
});

it('disables free-text search when the backend cannot support it', function (): void {
    config()->set('eagle-eye.default', 'null');
    app(DriverManager::class)->forgetDrivers();

    Livewire::test(ListActivityLogs::class)
        ->assertOk()
        ->assertSee('Free-text search is not supported by the active log backend.');
});

it('denies the explorer when access is unavailable', function (): void {
    Gate::define('viewActivityLog', static fn (?object $viewer = null): bool => false);

    Livewire::test(ListActivityLogs::class)->assertForbidden();
});

it('reauthorizes the explorer after access is revoked', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000R1', 'Revocation probe');
    $component = Livewire::test(ListActivityLogs::class);

    Gate::define('viewActivityLog', static fn (?object $viewer = null): bool => false);

    $component->call('getActivityPage')->assertForbidden();
});

it('locks the cursor history across requests', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000L1', 'Lock probe');

    expect(fn () => Livewire::test(ListActivityLogs::class)->set('activityPageCursors', [1 => 'tampered']))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('returns to details and shows origin when switching to an entry without changes', function (): void {
    EagleEye::fake();
    $withChanges = companionEntry('01J0000000000000000000SW', 'Has changes', ActivityChannel::Model, payload: [
        'changes' => ['status' => ['old' => 'draft', 'new' => 'approved']],
    ]);
    $withoutChanges = companionEntry('01J0000000000000000000SX', 'No changes', ActivityChannel::Model, payload: []);

    $component = Livewire::test(ExplorerPane::class, ['initialId' => $withChanges->id]);
    $component->assertSee('Changes');

    $component->call('select', $withoutChanges->id);
    $component->assertDontSee('Changes')
        ->assertSee('Origin')
        ->assertSee('x-data="{ tab: \'details\' }"', false);
});
