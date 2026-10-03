<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Filament\Widgets\Overview;

use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Enums\ActivitySeverity;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource;
use Dotswan\EagleEyeFilament\Support\Presentation\ActivityNav;
use Throwable;

final class OverviewFailures extends OverviewSection
{
    protected string $view = 'eagle-eye-filament::widgets.overview.failures';

    public const int LIMIT = 20;

    /** @var array{count: int, approximate: bool, title: ?string, url: ?string}|null */
    private ?array $alert = null;

    private bool $resolved = false;

    private bool $failed = false;

    public function backendUnavailable(): bool
    {
        $this->authorizeAccess();
        $this->getAlert();

        return $this->failed;
    }

    /** @return array{count: int, approximate: bool, title: ?string, url: ?string}|null */
    public function getAlert(): ?array
    {
        $this->authorizeAccess();

        if ($this->resolved) {
            return $this->alert;
        }

        $this->resolved = true;

        try {
            $result = $this->repository()->search(
                (new ActivityQuery())
                    ->severities(
                        ActivitySeverity::Error,
                        ActivitySeverity::Critical,
                        ActivitySeverity::Alert,
                        ActivitySeverity::Emergency,
                    )
                    ->last('1 hour')
                    ->limit(self::LIMIT),
            );
        } catch (Throwable) {
            $this->failed = true;

            return $this->alert = null;
        }

        $traces = [];

        foreach ($result->entries as $entry) {
            $traces[$entry->trace->traceId] = true;
        }

        $count = count($traces);

        if (0 === $count) {
            return $this->alert = null;
        }

        $latest = $result->entries[0];

        return $this->alert = [
            'count'       => $count,
            'approximate' => $result->hasMore,
            'title'       => $latest->title,
            'url'         => ActivityLogResource::getUrl('view', ['record' => $latest->id]),
        ];
    }

    public function getExplorerUrl(): string
    {
        $this->authorizeAccess();

        return ActivityNav::explorerUrl([
            'severity' => [
                ActivitySeverity::Error->value,
                ActivitySeverity::Critical->value,
                ActivitySeverity::Alert->value,
                ActivitySeverity::Emergency->value,
            ],
        ], '1h');
    }
}
