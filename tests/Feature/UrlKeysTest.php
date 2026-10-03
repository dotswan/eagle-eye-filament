<?php

declare(strict_types=1);

use Dotswan\EagleEye\Facades\EagleEye;
use Dotswan\EagleEyeFilament\Filament\Livewire\ExplorerPane;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Livewire;

beforeEach(function (): void {
    Gate::define('viewActivityLog', static fn (?object $viewer = null): bool => true);
    Gate::define('revealActivityLog', static fn (?object $viewer = null): bool => true);
});

it('never reuses a filament reserved query key as a url alias', function (ReflectionClass $reflection): void {
    $reserved = [
        'action', 'actionArguments', 'actionContext', 'tableAction', 'tableActionRecord',
        'tableActionArguments', 'tableFilters', 'tableGrouping', 'tableGroupingDirection',
        'tableSearch', 'tableSort', 'tableColumnSearches', 'activeTab', 'page',
    ];

    $class = $reflection->getName();
    $aliases = [];

    foreach ($reflection->getProperties() as $property) {
        if ($property->getDeclaringClass()->getName() !== $class) {
            continue;
        }

        foreach ($property->getAttributes(Url::class) as $attribute) {
            $instance = $attribute->newInstance();
            $aliases[] = $instance->as ?? $property->getName();
        }
    }

    expect($aliases)->not->toBeEmpty()
        ->and(array_intersect($aliases, $reserved))->toBe([]);
})->with([
    'explorer' => [new ReflectionClass(ListActivityLogs::class)],
    'pane'     => [new ReflectionClass(ExplorerPane::class)],
]);

it('hydrates the actions filter without emitting a mountAction request', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000U1', 'Action filter probe');

    $component = Livewire::withQueryParams(['actions' => ['http.request']])->test(ListActivityLogs::class);
    $component->assertOk();

    expect(explorerInstance($component->instance())->actions)->toBe(['http.request'])
        ->and($component->html())->not->toContain('mountAction(');
});

it('ignores the filament reserved action key without a server error', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000U2', 'Legacy key probe');

    $component = Livewire::withQueryParams(['action' => ['http.request']])->test(ListActivityLogs::class);
    $component->assertOk();

    expect(explorerInstance($component->instance())->actions)->toBe([])
        ->and($component->html())->not->toContain('mountAction(');
});
