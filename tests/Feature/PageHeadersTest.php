<?php

declare(strict_types=1);

use Dotswan\EagleEye\Facades\EagleEye;
use Dotswan\EagleEyeFilament\Filament\Pages\RecentTracesPage;
use Dotswan\EagleEyeFilament\Filament\Pages\TraceTimelinePage;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource\Pages\ViewActivityLog;
use Dotswan\EagleEyeFilament\Filament\Widgets\Overview\OverviewFailures;
use Livewire\Livewire;

beforeEach(function (): void {
    Gate::define('viewActivityLog', static fn (?object $viewer = null): bool => true);
    Gate::define('revealActivityLog', static fn (?object $viewer = null): bool => true);
});

it('renders a shell header on every drill-down page', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000H1', 'Header probe', traceId: str_repeat('b', 32));

    expect(Livewire::test(RecentTracesPage::class)->html())->toContain('ee-page__eyebrow')
        ->and(Livewire::test(ViewActivityLog::class, ['record' => '01J0000000000000000000H1'])->html())->toContain('ee-page__eyebrow')
        ->and(Livewire::withQueryParams(['trace' => str_repeat('b', 32)])->test(TraceTimelinePage::class)->html())->toContain('ee-page__eyebrow')
        ->and(actorPage()->html())->toContain('ee-page__eyebrow')
        ->and(subjectPage()->html())->toContain('ee-page__eyebrow');
});

it('carries the drilled-down range into the explorer url', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000H2', 'Range link probe', traceId: str_repeat('c', 32));

    expect(actorInstance(actorPage()->instance())->getExplorerUrl())->toContain('range=7d')
        ->and(failuresInstance(Livewire::test(OverviewFailures::class, ['range' => '24h'])->instance())->getExplorerUrl())->toContain('range=1h')
        ->and(subjectPage()->html())->toContain('range=30d');
});

it('exposes the explorer range and per page in the url', function (): void {
    $url = ListActivityLogs::getUrl(['range' => '7d', 'per_page' => 50]);

    expect($url)->toContain('range=7d')->toContain('per_page=50');
});
