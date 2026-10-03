<?php

declare(strict_types=1);

use Dotswan\EagleEye\Contracts\Resolution\FieldLabelResolverInterface;
use Dotswan\EagleEye\Facades\EagleEye;
use Dotswan\EagleEyeFilament\Filament\Pages\SubjectHistoryPage;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function (): void {
    Gate::define('viewActivityLog', static fn (?object $viewer = null): bool => true);
    Gate::define('revealActivityLog', static fn (?object $viewer = null): bool => true);
});

it('rejects a missing or tampered subject context at mount', function (): void {
    EagleEye::fake();

    Livewire::test(SubjectHistoryPage::class)->assertNotFound();
    Livewire::withQueryParams(['subject_type' => 'order'])->test(SubjectHistoryPage::class)->assertNotFound();
    Livewire::withQueryParams(['subject_type' => 'order', 'subject_id' => "77213\n"])->test(SubjectHistoryPage::class)->assertNotFound();
});

it('locks the subject context across requests', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000S1', 'Subject lock probe');

    expect(fn () => subjectPage()->set('subjectType', 'invoice'))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    expect(fn () => subjectPage()->set('subjectId', '9911'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('rejects an unknown subject page jump', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000S2', 'Jump probe');

    subjectPage()->set('page', 7)->assertStatus(422);
});

it('renders version history with diffs, actors and event links', function (): void {
    EagleEye::fake();
    app()->instance(FieldLabelResolverInterface::class, new class () implements FieldLabelResolverInterface {
        public function resolve(object|string $subject, string $field): string
        {
            return 'Workflow state';
        }
    });
    companionEntry('01J0000000000000000000S3', 'Order status changed', payload: [
        'changes' => ['status' => ['old' => 'draft', 'new' => 'approved']],
    ]);

    $component = subjectPage();

    $component->assertOk()
        ->assertSee('Order status changed')
        ->assertSee('Workflow state')
        ->assertSee('draft')
        ->assertSee('approved')
        ->assertSee('event ↗');

    $versions = subjectInstance($component->instance())->getVersions();

    expect($versions)->toHaveCount(1)
        ->and($versions[0]['changes'])->toHaveCount(1)
        ->and($versions[0]['actor'])->toBe('Sara Bakker')
        ->and($versions[0]['time'])->toMatch('/^\d{2}:\d{2}:\d{2}\.\d{3}$/')
        ->and($versions[0]['url'])->toBeString();
});

it('builds subject stats for events editors and first seen', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000S4', 'Edit one', actorId: 'a');
    companionEntry('01J0000000000000000000S5', 'Edit two', actorId: 'b');
    companionEntry('01J0000000000000000000S6', 'Edit three', actorId: 'a');

    $component = subjectPage()->call('loadStats');
    $component->assertOk()->assertSee('Events')->assertSee('Editors')->assertSee('First seen');
    $stats = subjectInstance($component->instance())->getStats();

    expect($stats['events'])->toBe(3)
        ->and($stats['eventsApprox'])->toBeFalse()
        ->and($stats['editors'])->toBe('2')
        ->and($stats['editorsExact'])->toBeTrue()
        ->and($stats['firstSeen'])->toBeString()
        ->and($stats['firstSeen'])->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/')
        ->and($stats['firstSeenApprox'])->toBeFalse();
});

it('falls back to a bounded editor label when the aggregate group limit is hit', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000S7', 'Edit', actorId: 'a');
    $spy = spyActivityRepository();
    $spy->failAggregatesWithLength = true;

    $component = subjectPage()->call('loadStats');

    $component->assertOk()->assertSee('1000+');

    $stats = subjectInstance($component->instance())->getStats();

    expect($stats['editors'])->toBe('1000+')
        ->and($stats['editorsExact'])->toBeFalse();
});

it('pages the version history twenty per page', function (): void {
    EagleEye::fake();
    $at = new DateTimeImmutable('-90 minutes', new DateTimeZone('UTC'));

    for ($sequence = 1; $sequence <= 25; $sequence++) {
        companionEntry(sprintf('01J%021d', 530_000 + $sequence), 'Subject version '.$sequence, loggedAt: $at->modify('+'.$sequence.' seconds'));
    }

    $component = subjectPage();
    $first = subjectInstance($component->instance())->getActivityPage();

    expect($first->hasPrevious)->toBeFalse()
        ->and($first->hasNext)->toBeTrue()
        ->and($first->entries)->toHaveCount(20);

    $component->call('nextPage');
    $second = subjectInstance($component->instance())->getActivityPage();

    expect($second->hasPrevious)->toBeTrue()
        ->and($second->hasNext)->toBeFalse()
        ->and($second->entries)->toHaveCount(5);
});

it('escapes attacker controlled version titles', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000S8', '<script>alert(1)</script>');

    subjectPage()
        ->assertSee('<script>alert(1)</script>')
        ->assertDontSee('<script>', false);
});

it('renders json column changes as path rows', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000S9', 'Preferences changed', payload: [
        'changes' => ['preferences' => [
            'old' => ['theme' => 'light', 'locale' => 'en'],
            'new' => ['theme' => 'dark', 'locale' => 'en'],
        ]],
    ]);

    $component = subjectPage();
    $versions = subjectInstance($component->instance())->getVersions();

    /** @var array{kind?: string, paths?: list<array{path: string, before: string, after: string}>} $change */
    $change = $versions[0]['changes'][0];

    expect($versions)->toHaveCount(1)
        ->and($change['kind'] ?? null)->toBe('structured')
        ->and($change['paths'] ?? [])->not->toBeEmpty();

    expect(array_column($change['paths'] ?? [], 'path'))->toContain('theme');
});
