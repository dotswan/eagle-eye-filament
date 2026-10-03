<?php

declare(strict_types=1);

use Dotswan\EagleEye\Contracts\Driver\ActivityLogDriverFactoryInterface;
use Dotswan\EagleEye\Contracts\Driver\ActivityLogMaintenanceDriverInterface;
use Dotswan\EagleEye\Contracts\Driver\FullActivityLogDriverInterface;
use Dotswan\EagleEye\Contracts\Repository\ActivityRepositoryInterface;
use Dotswan\EagleEye\Data\ActivityDraft;
use Dotswan\EagleEye\Data\ActivityEntry;
use Dotswan\EagleEye\Data\Actor;
use Dotswan\EagleEye\Data\RequestSnapshot;
use Dotswan\EagleEye\Data\SecretRef;
use Dotswan\EagleEye\Data\Subject;
use Dotswan\EagleEye\Data\TraceContext;
use Dotswan\EagleEye\Drivers\DriverManager;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Enums\ActivitySeverity;
use Dotswan\EagleEye\Enums\Capability;
use Dotswan\EagleEyeFilament\Filament\Livewire\ExplorerPane;
use Dotswan\EagleEyeFilament\Filament\Pages\RecentTracesPage;
use Dotswan\EagleEyeFilament\Filament\Pages\SubjectHistoryPage;
use Dotswan\EagleEyeFilament\Filament\Pages\TraceTimelinePage;
use Dotswan\EagleEyeFilament\Filament\Pages\UserActivityPage;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource\Pages\ViewActivityLog;
use Dotswan\EagleEyeFilament\Filament\Widgets\Overview\OverviewBusiestActors;
use Dotswan\EagleEyeFilament\Filament\Widgets\Overview\OverviewFailures;
use Dotswan\EagleEyeFilament\Filament\Widgets\Overview\OverviewLatestRequests;
use Dotswan\EagleEyeFilament\Filament\Widgets\Overview\OverviewTraffic;
use Dotswan\EagleEyeFilament\Tests\Support\CallCountingRepository;
use Dotswan\EagleEyeFilament\Tests\Support\CapabilityFakingDriver;
use Dotswan\EagleEyeFilament\Tests\TestCase;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(TestCase::class)->in('Feature');

/**
 * @param array<string, mixed> $payload
 * @param array<string, SecretRef> $secrets
 */
function companionEntry(
    string $id,
    string $title,
    ActivityChannel $channel = ActivityChannel::Custom,
    ActivitySeverity $severity = ActivitySeverity::Info,
    array $payload = [],
    string $actorId = '8821',
    ?DateTimeImmutable $loggedAt = null,
    ?string $traceId = null,
    ?string $subjectType = 'order',
    ?string $subjectId = '77213',
    ?string $subjectLabel = 'Order 77213',
    string $action = 'recorded',
    array $secrets = [],
    ?RequestSnapshot $request = null,
    ?string $causationId = null,
): ActivityEntry {
    $draft = ActivityDraft::make($action, $channel, $severity)
        ->withActor(new Actor('user', $actorId, 'Sara Bakker'))
        ->withTags(['shop'])
        ->withPayload($payload);

    if (null !== $subjectType) {
        $draft->withSubject(new Subject($subjectType, $subjectId ?? '', $subjectLabel));
    }

    $draft->id = $id;
    $draft->title = $title;
    $draft->trace = new TraceContext($traceId ?? str_repeat('a', 32), $id, $causationId);
    $draft->loggedAt = $loggedAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $draft->createdAt = $draft->loggedAt;
    $draft->secrets = $secrets;
    $draft->request = $request;

    $entry = $draft->freeze();
    app(ActivityLogDriverFactoryInterface::class)->driver()->write($entry);

    return $entry;
}

function useCompanionDatabaseDriver(): void
{
    config()->set('eagle-eye.default', 'database');
    app(DriverManager::class)->forgetDrivers();
    $driver = app(ActivityLogDriverFactoryInterface::class)->driver();

    if (! $driver instanceof ActivityLogMaintenanceDriverInterface) {
        throw new LogicException('Database activity driver is unavailable.');
    }

    $driver->install();
}

function spyActivityRepository(): CallCountingRepository
{
    $spy = new CallCountingRepository(app(ActivityRepositoryInterface::class));
    app()->instance(ActivityRepositoryInterface::class, $spy);

    return $spy;
}

/** @param list<Capability> $supported */
function fakeSupportedCapabilities(array $supported): void
{
    $real = app(ActivityLogDriverFactoryInterface::class)->driver();

    if (! $real instanceof FullActivityLogDriverInterface) {
        throw new LogicException('The active driver is not a full driver.');
    }

    $manager = app(DriverManager::class);
    $manager->extend('memory', static fn (): CapabilityFakingDriver => new CapabilityFakingDriver($real, $supported));
    $manager->forgetDrivers();
    app()->forgetInstance(ActivityRepositoryInterface::class);
}

function explorerInstance(Component $component): ListActivityLogs
{
    if (! $component instanceof ListActivityLogs) {
        throw new LogicException('Explorer component unavailable.');
    }

    return $component;
}

function explorerPaneInstance(Component $component): ExplorerPane
{
    if (! $component instanceof ExplorerPane) {
        throw new LogicException('Explorer pane component unavailable.');
    }

    return $component;
}

function detailInstance(Component $component): ViewActivityLog
{
    if (! $component instanceof ViewActivityLog) {
        throw new LogicException('Detail component unavailable.');
    }

    return $component;
}

function traceInstance(Component $component): TraceTimelinePage
{
    if (! $component instanceof TraceTimelinePage) {
        throw new LogicException('Trace component unavailable.');
    }

    return $component;
}

function tracesInstance(Component $component): RecentTracesPage
{
    if (! $component instanceof RecentTracesPage) {
        throw new LogicException('Traces component unavailable.');
    }

    return $component;
}

function actorInstance(Component $component): UserActivityPage
{
    if (! $component instanceof UserActivityPage) {
        throw new LogicException('Actor component unavailable.');
    }

    return $component;
}

function subjectInstance(Component $component): SubjectHistoryPage
{
    if (! $component instanceof SubjectHistoryPage) {
        throw new LogicException('Subject component unavailable.');
    }

    return $component;
}

/** @return Testable<Component> */
function actorPage(string $type = 'user', string $id = '8821'): Testable
{
    return Livewire::withQueryParams(['actor_type' => $type, 'actor_id' => $id])->test(UserActivityPage::class);
}

/** @return Testable<Component> */
function subjectPage(string $type = 'order', string $id = '77213'): Testable
{
    return Livewire::withQueryParams(['subject_type' => $type, 'subject_id' => $id])->test(SubjectHistoryPage::class);
}

function trafficInstance(Component $component): OverviewTraffic
{
    if (! $component instanceof OverviewTraffic) {
        throw new LogicException('Traffic component unavailable.');
    }

    return $component;
}

function failuresInstance(Component $component): OverviewFailures
{
    if (! $component instanceof OverviewFailures) {
        throw new LogicException('Failures component unavailable.');
    }

    return $component;
}

function latestRequestsInstance(Component $component): OverviewLatestRequests
{
    if (! $component instanceof OverviewLatestRequests) {
        throw new LogicException('Latest requests component unavailable.');
    }

    return $component;
}

function busiestActorsInstance(Component $component): OverviewBusiestActors
{
    if (! $component instanceof OverviewBusiestActors) {
        throw new LogicException('Busiest actors component unavailable.');
    }

    return $component;
}
