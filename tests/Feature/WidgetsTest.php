<?php

declare(strict_types=1);

use Dotswan\EagleEye\Contracts\Repository\ActivityRepositoryInterface;
use Dotswan\EagleEye\Contracts\Resolution\ActivityIdentityResolverInterface;
use Dotswan\EagleEye\Data\Actor;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Enums\ActivitySeverity;
use Dotswan\EagleEye\Facades\EagleEye;
use Dotswan\EagleEyeFilament\EagleEyeFilamentPlugin;
use Dotswan\EagleEyeFilament\Filament\Pages\ActivityOverviewPage;
use Dotswan\EagleEyeFilament\Filament\Pages\RecentTracesPage;
use Dotswan\EagleEyeFilament\Filament\Pages\SubjectHistoryPage;
use Dotswan\EagleEyeFilament\Filament\Pages\TraceTimelinePage;
use Dotswan\EagleEyeFilament\Filament\Pages\UserActivityPage;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource;
use Dotswan\EagleEyeFilament\Filament\Widgets\ActivityTimelineWidget;
use Dotswan\EagleEyeFilament\Filament\Widgets\ActivityVolumeChart;
use Dotswan\EagleEyeFilament\Filament\Widgets\ErrorRateChart;
use Dotswan\EagleEyeFilament\Filament\Widgets\TopActorsChart;
use Filament\Panel;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function (): void {
    Gate::define('viewActivityLog', static fn (?object $viewer = null): bool => true);
    Gate::define('revealActivityLog', static fn (?object $viewer = null): bool => true);
});

it('ranks all actors in the bounded time window and resolves names in one batch', function (): void {
    EagleEye::fake();

    for ($sequence = 1; $sequence <= 260; $sequence++) {
        companionEntry(sprintf('01J%021d', 20_000 + $sequence), 'Rank A', actorId: 'a');
    }

    for ($sequence = 1; $sequence <= 10; $sequence++) {
        companionEntry(sprintf('01J%021d', 30_000 + $sequence), 'Rank B', actorId: 'b');
    }

    for ($sequence = 1; $sequence <= 5; $sequence++) {
        companionEntry(sprintf('01J%021d', 35_000 + $sequence), 'Rank C', actorId: 'c');
    }

    $resolver = new class () implements ActivityIdentityResolverInterface {
        public int $calls = 0;

        public function resolveActors(array $actors): array
        {
            $this->calls++;

            return array_map(
                static fn (Actor $actor): Actor => new Actor($actor->type, $actor->id, 'c' === $actor->id ? null : 'Resolved '.$actor->id),
                $actors,
            );
        }

        public function resolveSubjects(array $subjects): array
        {
            return $subjects;
        }
    };
    app()->instance(ActivityIdentityResolverInterface::class, $resolver);

    $widget = Livewire::test(TopActorsChart::class)->instance();
    $callsBeforeExplicitRead = $resolver->calls;
    $method = new ReflectionMethod($widget, 'getData');
    $data = $method->invoke($widget);

    expect($data['datasets'][0]['data'])->toBe([260, 10, 5])
        ->and($data['labels'])->toBe(['Resolved a', 'Resolved b', 'user:c'])
        ->and($resolver->calls)->toBe($callsBeforeExplicitRead + 1);
});

it('renders an explicit unavailable state when an exact actor cutoff cannot be proven', function (): void {
    EagleEye::fake();
    $repository = Mockery::mock(ActivityRepositoryInterface::class);
    $repository->shouldReceive('aggregateTop')->once()->andThrow(new LengthException('Loki ranking cutoff is ambiguous.'));
    app()->instance(ActivityRepositoryInterface::class, $repository);
    $resolver = Mockery::mock(ActivityIdentityResolverInterface::class);
    $resolver->shouldNotReceive('resolveActors');
    app()->instance(ActivityIdentityResolverInterface::class, $resolver);

    $component = Livewire::test(TopActorsChart::class);
    $component->assertOk();
    $widget = $component->instance();

    if (! $widget instanceof TopActorsChart) {
        throw new LogicException('Expected the top actors widget.');
    }

    expect($widget->getDescription())->toBe('Actor ranking unavailable because the selected backend cannot prove an exact cutoff.');
});

it('clears the unavailable description when actor ranking recovers on the same widget', function (): void {
    EagleEye::fake();
    $repository = Mockery::mock(ActivityRepositoryInterface::class);
    $repository->shouldReceive('aggregateTop')->once()->ordered()->andThrow(new LengthException('Ambiguous cutoff.'));
    $repository->shouldReceive('aggregateTop')->once()->ordered()->andReturn(['["user","a"]' => 10]);
    $repository->shouldReceive('aggregateTop')->once()->ordered()->andThrow(new LengthException('Ambiguous cutoff.'));
    app()->instance(ActivityRepositoryInterface::class, $repository);
    $resolver = Mockery::mock(ActivityIdentityResolverInterface::class);
    $resolver->shouldReceive('resolveActors')->once()->andReturn(['["user","a"]' => new Actor('user', 'a', 'Recovered actor')]);
    app()->instance(ActivityIdentityResolverInterface::class, $resolver);

    $component = Livewire::test(TopActorsChart::class);
    $component->assertOk();
    $widget = $component->instance();

    if (! $widget instanceof TopActorsChart) {
        throw new LogicException('Expected the top actors widget.');
    }

    $message = 'Actor ranking unavailable because the selected backend cannot prove an exact cutoff.';
    expect($widget->getDescription())->toBe($message);
    $method = new ReflectionMethod($widget, 'getData');
    $data = $method->invoke($widget);

    expect($widget->getDescription())->toBeNull()
        ->and($data['datasets'][0]['data'])->toBe([10])
        ->and($data['labels'])->toBe(['Recovered actor']);

    $data = $method->invoke($widget);

    expect($widget->getDescription())->toBe($message)
        ->and($data['datasets'][0]['data'])->toBe([]);
});

it('registers the resource pages and configurable dashboard widgets', function (): void {
    $panel = Panel::make()->id('eagle-eye-default');
    $plugin = EagleEyeFilamentPlugin::make();
    $plugin->register($panel);
    $plugin->boot($panel);

    expect($plugin->getId())->toBe('eagle-eye')
        ->and($panel->getResources())->toContain(ActivityLogResource::class)
        ->and($panel->getPages())->toContain(ActivityOverviewPage::class, TraceTimelinePage::class, UserActivityPage::class, RecentTracesPage::class, SubjectHistoryPage::class)
        ->and($panel->getWidgets())->toContain(ActivityTimelineWidget::class, ActivityVolumeChart::class, TopActorsChart::class, ErrorRateChart::class);

    $withoutWidgets = Panel::make()->id('eagle-eye-without-widgets');
    EagleEyeFilamentPlugin::make()->widgets([ActivityVolumeChart::class])->withoutWidgets()->register($withoutWidgets);

    expect($withoutWidgets->getWidgets())->toBe([]);
});

it('applies the plugin navigation group and sort to the sidebar items', function (): void {
    expect(ActivityOverviewPage::getNavigationGroup())->toBeNull()
        ->and(ActivityOverviewPage::getNavigationSort())->toBe(-1)
        ->and(ActivityLogResource::getNavigationGroup())->toBeNull()
        ->and(ActivityLogResource::getNavigationSort())->toBe(0);

    $plugin = EagleEyeFilamentPlugin::current();

    if (! $plugin instanceof EagleEyeFilamentPlugin) {
        throw new RuntimeException('Expected the plugin on the testing panel.');
    }

    $plugin->navigationGroup('Monitoring')->navigationSort(10);

    expect(ActivityOverviewPage::getNavigationGroup())->toBe('Monitoring')
        ->and(ActivityOverviewPage::getNavigationSort())->toBe(10)
        ->and(ActivityLogResource::getNavigationGroup())->toBe('Monitoring')
        ->and(ActivityLogResource::getNavigationSort())->toBe(11);
});

it('does not poll the dashboard chart widgets', function (string $widget): void {
    $instance = Livewire::test($widget)->instance();
    $method = new ReflectionMethod($instance, 'getPollingInterval');

    expect($method->invoke($instance))->toBeNull();
})->with([
    'volume'     => [ActivityVolumeChart::class],
    'top actors' => [TopActorsChart::class],
    'error rate' => [ErrorRateChart::class],
]);

it('builds bounded volume and error charts from repository aggregates', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000C1', 'Informational chart entry');
    companionEntry('01J0000000000000000000C2', 'Error chart entry', severity: ActivitySeverity::Error);

    $volume = Livewire::test(ActivityVolumeChart::class)->instance();
    $errorRate = Livewire::test(ErrorRateChart::class)->instance();
    $data = new ReflectionMethod($volume, 'getData');
    $type = new ReflectionMethod($volume, 'getType');
    $errorData = new ReflectionMethod($errorRate, 'getData');
    $errorType = new ReflectionMethod($errorRate, 'getType');

    expect($data->invoke($volume))->toMatchArray([
        'datasets' => [['label' => 'Entries', 'data' => [2]]],
        'labels'   => [ActivityChannel::Custom->value],
    ])->and($type->invoke($volume))->toBe('bar')
        ->and($errorData->invoke($errorRate))->toMatchArray([
            'datasets' => [['label' => 'Entries', 'data' => [1, 1]]],
            'labels'   => ['errors', 'other'],
        ])->and($errorType->invoke($errorRate))->toBe('doughnut');
});

it('degrades charts safely when aggregation is rejected', function (): void {
    EagleEye::fake();
    $repository = Mockery::mock(ActivityRepositoryInterface::class);
    $repository->shouldReceive('aggregate')->times(4)->andThrow(new InvalidArgumentException('unsupported'));
    app()->instance(ActivityRepositoryInterface::class, $repository);
    $volume = Livewire::test(ActivityVolumeChart::class)->instance();
    $errorRate = Livewire::test(ErrorRateChart::class)->instance();

    expect((new ReflectionMethod($volume, 'getData'))->invoke($volume))->toMatchArray([
        'datasets' => [['label' => 'Entries', 'data' => []]],
        'labels'   => [],
    ])->and((new ReflectionMethod($errorRate, 'getData'))->invoke($errorRate))->toMatchArray([
        'datasets' => [['label' => 'Entries', 'data' => [0, 0]]],
        'labels'   => ['errors', 'other'],
    ])->and(ActivityLogResource::getModelLabel())->toBe('activity entry')
        ->and(ActivityLogResource::getPluralModelLabel())->toBe('activity entries')
        ->and(ActivityLogResource::shouldSkipAuthorization())->toBeTrue();
});

it('renders subject entries and a subject history footer link in the timeline widget', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000W1', 'Order status changed');

    Livewire::test(ActivityTimelineWidget::class, ['subjectType' => 'order', 'subjectId' => '77213'])
        ->assertSee('Order status changed')
        ->assertSee('View full history →')
        ->assertSee(SubjectHistoryPage::getUrl(['subject_type' => 'order', 'subject_id' => '77213']));
});

it('hides the subject history footer link without a subject', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000W2', 'Unscoped activity');

    Livewire::test(ActivityTimelineWidget::class)
        ->assertSee('Unscoped activity')
        ->assertDontSee('View full history');
});
