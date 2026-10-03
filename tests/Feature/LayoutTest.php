<?php

declare(strict_types=1);

use Dotswan\EagleEyeFilament\Filament\Concerns\HasFullContentWidth;
use Dotswan\EagleEyeFilament\Filament\Pages\ActivityOverviewPage;
use Dotswan\EagleEyeFilament\Filament\Pages\RecentTracesPage;
use Dotswan\EagleEyeFilament\Filament\Pages\SubjectHistoryPage;
use Dotswan\EagleEyeFilament\Filament\Pages\TraceTimelinePage;
use Dotswan\EagleEyeFilament\Filament\Pages\UserActivityPage;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource\Pages\ViewActivityLog;
use Filament\Pages\BasePage;
use Filament\Support\Enums\Width;

it('renders every eagle eye page at full content width', function (string $page): void {
    expect(class_uses_recursive($page))->toContain(HasFullContentWidth::class);

    $instance = app($page);

    if (! $instance instanceof BasePage) {
        throw new LogicException('Eagle Eye page must extend the Filament base page.');
    }

    expect($instance->getMaxContentWidth())->toBe(Width::Full);
})->with([
    'overview'      => [ActivityOverviewPage::class],
    'explorer'      => [ListActivityLogs::class],
    'detail'        => [ViewActivityLog::class],
    'trace'         => [TraceTimelinePage::class],
    'recent traces' => [RecentTracesPage::class],
    'actor'         => [UserActivityPage::class],
    'subject'       => [SubjectHistoryPage::class],
]);
