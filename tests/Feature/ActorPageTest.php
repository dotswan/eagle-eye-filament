<?php

declare(strict_types=1);

use Dotswan\EagleEye\Contracts\Resolution\ActivityIdentityResolverInterface;
use Dotswan\EagleEye\Data\Actor;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Enums\ActivitySeverity;
use Dotswan\EagleEye\Facades\EagleEye;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    Gate::define('viewActivityLog', static fn (?object $viewer = null): bool => true);
    Gate::define('revealActivityLog', static fn (?object $viewer = null): bool => true);
});

function currentHour(): DateTimeImmutable
{
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    return $now->setTime((int) $now->format('H'), 0);
}

it('renders the actor profile with the unresolved label fallback and recent activity', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000P1', 'Actor recent event', actorId: '8821');

    $component = actorPage();

    $component->assertOk()
        ->assertSee('Actor recent event')
        ->assertSee('user:8821')
        ->assertSee('Open in explorer →');

    expect(actorInstance($component->instance())->getActorLabel())->toBe('user:8821');
});

it('resolves the actor display name through the identity resolver', function (): void {
    EagleEye::fake();
    app()->instance(ActivityIdentityResolverInterface::class, new class () implements ActivityIdentityResolverInterface {
        public function resolveActors(array $actors): array
        {
            return array_map(
                static fn (Actor $actor): Actor => new Actor($actor->type, $actor->id, 'Sara Bakker'),
                $actors,
            );
        }

        public function resolveSubjects(array $subjects): array
        {
            return $subjects;
        }
    });

    $component = actorPage();

    $component->assertSee('Sara Bakker');

    expect(actorInstance($component->instance())->getActorLabel())->toBe('Sara Bakker');
});

it('builds stats, a 168 bucket heatmap and top actions from one series call', function (): void {
    EagleEye::fake();
    $hour = currentHour();

    companionEntry(sprintf('01J%021d', 500_001), 'Model write', ActivityChannel::Model, ActivitySeverity::Error, loggedAt: $hour->modify('+1 minute'), action: 'model.updated');
    companionEntry(sprintf('01J%021d', 500_002), 'Model write two', ActivityChannel::Model, ActivitySeverity::Info, loggedAt: $hour->modify('+2 minutes'), action: 'model.updated');
    companionEntry(sprintf('01J%021d', 500_003), 'Request', ActivityChannel::Http, ActivitySeverity::Info, loggedAt: $hour->modify('+3 minutes'), action: 'http.request');

    $component = actorPage()->call('loadInsights');
    $component->assertOk()->assertSee('Events')->assertSee('Writes')->assertSee('Errors');
    $instance = actorInstance($component->instance());

    $stats = $instance->getStats();

    expect($stats['events'])->toBe(3)
        ->and($stats['writes'])->toBe(2)
        ->and($stats['errors'])->toBe(1)
        ->and($stats['errorsAvailable'])->toBeTrue()
        ->and($stats['exact'])->toBeTrue();

    $heatmap = $instance->getHeatmap();

    expect($heatmap['rows'])->toHaveCount(7)
        ->and($heatmap['rows'][0]['cells'])->toHaveCount(24)
        ->and($heatmap['total'])->toBe(3);

    $actions = $instance->getTopActions();

    expect($actions)->toHaveCount(2)
        ->and($actions[0]['action'])->toBe('model.updated')
        ->and($actions[0]['count'])->toBe(2)
        ->and($actions[0]['percent'])->toBe(100.0)
        ->and($actions[1]['action'])->toBe('http.request')
        ->and($actions[1]['percent'])->toBe(50.0);
});

it('builds heatmap buckets with the database driver', function (): void {
    useCompanionDatabaseDriver();
    companionEntry(sprintf('01J%021d', 510_001), 'Database heatmap', ActivityChannel::Model, actorId: '8821', loggedAt: currentHour()->modify('+1 minute'));

    $component = actorPage()->call('loadInsights');
    $component->assertOk();

    expect(actorInstance($component->instance())->getHeatmap()['total'])->toBe(1);
});

it('surfaces an unsupported state without discarding the page when series aggregation fails', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000P9', 'List survives', actorId: '8821');
    $spy = spyActivityRepository();
    $spy->failSeries = true;

    $component = actorPage()->call('loadInsights');

    $component->assertOk()
        ->assertSee('List survives')
        ->assertSee('Activity statistics are not supported by the active log backend.');

    expect(actorInstance($component->instance())->seriesUnavailable())->toBeTrue();
});

it('pages the recent activity list twenty per page', function (): void {
    EagleEye::fake();
    $at = new DateTimeImmutable('-90 minutes', new DateTimeZone('UTC'));

    for ($sequence = 1; $sequence <= 25; $sequence++) {
        companionEntry(sprintf('01J%021d', 520_000 + $sequence), 'Actor page '.$sequence, actorId: '8821', loggedAt: $at->modify('+'.$sequence.' seconds'));
    }

    $component = actorPage();
    $first = actorInstance($component->instance())->getActivityPage();

    expect($first->hasPrevious)->toBeFalse()
        ->and($first->hasNext)->toBeTrue()
        ->and($first->entries)->toHaveCount(20);

    $component->call('nextPage');
    $second = actorInstance($component->instance())->getActivityPage();

    expect($second->hasPrevious)->toBeTrue()
        ->and($second->hasNext)->toBeFalse()
        ->and($second->entries)->toHaveCount(5);
});
