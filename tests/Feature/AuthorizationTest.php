<?php

declare(strict_types=1);

use Dotswan\EagleEye\Facades\EagleEye;
use Dotswan\EagleEyeFilament\Filament\Livewire\ExplorerPane;
use Dotswan\EagleEyeFilament\Filament\Pages\ActivityOverviewPage;
use Dotswan\EagleEyeFilament\Filament\Pages\RecentTracesPage;
use Dotswan\EagleEyeFilament\Filament\Pages\SubjectHistoryPage;
use Dotswan\EagleEyeFilament\Filament\Pages\TraceTimelinePage;
use Dotswan\EagleEyeFilament\Filament\Pages\UserActivityPage;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource\Pages\ViewActivityLog;
use Dotswan\EagleEyeFilament\Filament\Widgets\ActivityTimelineWidget;
use Dotswan\EagleEyeFilament\Filament\Widgets\ActivityVolumeChart;
use Dotswan\EagleEyeFilament\Filament\Widgets\ErrorRateChart;
use Dotswan\EagleEyeFilament\Filament\Widgets\Overview\OverviewTraffic;
use Dotswan\EagleEyeFilament\Filament\Widgets\TopActorsChart;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function (): void {
    Gate::define('viewActivityLog', static fn (?object $viewer = null): bool => true);
    Gate::define('revealActivityLog', static fn (?object $viewer = null): bool => true);
});

it('denies every page and widget when log access is unavailable', function (): void {
    Gate::define('viewActivityLog', static fn (?object $viewer = null): bool => false);

    Livewire::test(ListActivityLogs::class)->assertForbidden();
    Livewire::test(ExplorerPane::class, ['initialId' => null])->assertForbidden();
    Livewire::test(ActivityOverviewPage::class)->assertForbidden();
    Livewire::test(OverviewTraffic::class, ['range' => '24h'])->assertForbidden();
    Livewire::withQueryParams(['trace' => str_repeat('a', 32)])->test(TraceTimelinePage::class)->assertForbidden();
    Livewire::test(RecentTracesPage::class)->assertForbidden();
    Livewire::withQueryParams(['actor_type' => 'user', 'actor_id' => '8821'])->test(UserActivityPage::class)->assertForbidden();
    Livewire::withQueryParams(['subject_type' => 'order', 'subject_id' => '77213'])->test(SubjectHistoryPage::class)->assertForbidden();
    Livewire::test(ViewActivityLog::class, ['record' => '01J0000000000000000000Z1'])->assertForbidden();
    Livewire::test(ActivityTimelineWidget::class)->assertForbidden();
    Livewire::test(ActivityVolumeChart::class)->assertForbidden();
    Livewire::test(ErrorRateChart::class)->assertForbidden();
    Livewire::test(TopActorsChart::class)->assertForbidden();
});

it('reauthorizes pages and widgets after access is revoked', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000R1', 'Revocation probe');
    $detail = Livewire::test(ViewActivityLog::class, ['record' => '01J0000000000000000000R1']);
    $overview = Livewire::test(ActivityOverviewPage::class);
    $traffic = Livewire::test(OverviewTraffic::class, ['range' => '24h']);
    $trace = Livewire::withQueryParams(['trace' => str_repeat('a', 32)])->test(TraceTimelinePage::class);
    $traces = Livewire::test(RecentTracesPage::class);
    $explorer = Livewire::test(ListActivityLogs::class);
    $pane = Livewire::test(ExplorerPane::class, ['initialId' => null]);
    $actor = actorPage();
    $subject = subjectPage();

    Gate::define('viewActivityLog', static fn (?object $viewer = null): bool => false);

    $detail->call('getEntry')->assertForbidden();
    $overview->call('getRange')->assertForbidden();
    $traffic->call('getKpis')->assertForbidden();
    $trace->call('getStats')->assertForbidden();
    $traces->call('getActivityPage')->assertForbidden();
    $explorer->call('getActivityPage')->assertForbidden();
    $pane->call('getSelectedEntry')->assertForbidden();
    $actor->call('getActivityPage')->assertForbidden();
    $subject->call('getStats')->assertForbidden();
    expect(ActivityTimelineWidget::canView())->toBeFalse();
    Livewire::test(ActivityTimelineWidget::class)->assertForbidden();
});

it('rejects tampered contexts and cursors', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000T1', 'Tamper probe');

    Livewire::withQueryParams(['trace' => 'not-a-trace'])->test(TraceTimelinePage::class)->assertNotFound();
    Livewire::withQueryParams(['actor_type' => "user\n", 'actor_id' => '8821'])->test(UserActivityPage::class)->assertNotFound();
    Livewire::withQueryParams(['subject_type' => 'order', 'subject_id' => "77213\x7F"])->test(SubjectHistoryPage::class)->assertNotFound();

    expect(fn () => Livewire::test(ActivityTimelineWidget::class)->set('subjectType', 'invoice'))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    expect(fn () => Livewire::test(RecentTracesPage::class)->set('activityPageCursors', [1 => 'tampered']))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('locks valid record trace actor and subject contexts across requests', function (): void {
    EagleEye::fake();
    companionEntry('01J0000000000000000000L1', 'Locked context');

    expect(fn () => Livewire::test(ViewActivityLog::class, ['record' => '01J0000000000000000000L1'])
        ->set('recordId', '01J0000000000000000000L2'))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    expect(fn () => Livewire::withQueryParams(['trace' => str_repeat('a', 32)])
        ->test(TraceTimelinePage::class)
        ->set('traceId', str_repeat('b', 32)))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    expect(fn () => actorPage()->set('actorId', '9911'))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    expect(fn () => subjectPage()->set('subjectId', '9911'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});
