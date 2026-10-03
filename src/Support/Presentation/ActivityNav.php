<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Support\Presentation;

use Dotswan\EagleEye\Data\Subject;
use Dotswan\EagleEyeFilament\Filament\Pages\ActivityOverviewPage;
use Dotswan\EagleEyeFilament\Filament\Pages\RecentTracesPage;
use Dotswan\EagleEyeFilament\Filament\Pages\SubjectHistoryPage;
use Dotswan\EagleEyeFilament\Filament\Pages\TraceTimelinePage;
use Dotswan\EagleEyeFilament\Filament\Pages\UserActivityPage;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;

final class ActivityNav
{
    /** @return list<array{key: string, label: string, href: string, tone: string, badge: ?string}> */
    public static function items(): array
    {
        return [
            ['key' => 'overview', 'label' => 'Overview', 'href' => ActivityOverviewPage::getUrl(), 'tone' => 'neutral', 'badge' => null],
            ['key' => 'explorer', 'label' => 'Explorer', 'href' => ListActivityLogs::getUrl(), 'tone' => 'blue', 'badge' => null],
            ['key' => 'traces', 'label' => 'Traces', 'href' => RecentTracesPage::getUrl(), 'tone' => 'violet', 'badge' => null],
        ];
    }

    /** @param array<string, mixed> $filters */
    public static function explorerUrl(array $filters = [], string $range = '24h'): string
    {
        return ListActivityLogs::getUrl([...$filters, 'range' => $range]);
    }

    public static function traceUrl(string $traceId): ?string
    {
        if (1 !== preg_match('/^[a-f0-9]{32}$/', $traceId) || str_repeat('0', 32) === $traceId) {
            return null;
        }

        return TraceTimelinePage::getUrl(['trace' => $traceId]);
    }

    public static function subjectUrl(?Subject $subject): ?string
    {
        if (! $subject instanceof Subject || '' === $subject->id) {
            return null;
        }

        return SubjectHistoryPage::getUrl([
            'subject_type' => $subject->type,
            'subject_id'   => $subject->id,
        ]);
    }

    public static function actorUrl(?string $actorType, ?string $actorId): ?string
    {
        if (null === $actorType || '' === $actorType || null === $actorId || '' === $actorId) {
            return null;
        }

        return UserActivityPage::getUrl(['actor_type' => $actorType, 'actor_id' => $actorId]);
    }
}
