<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Filament\Widgets;

use Dotswan\EagleEye\Contracts\Repository\ActivityRepositoryInterface;
use Dotswan\EagleEye\Contracts\Security\LogAccessPolicyInterface;
use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Enums\Capability;
use Dotswan\EagleEyeFilament\Support\Capabilities;
use Filament\Widgets\ChartWidget;
use InvalidArgumentException;

final class ActivityVolumeChart extends ChartWidget
{
    protected ?string $heading = 'Activity volume by channel';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return app(LogAccessPolicyInterface::class)->canView(auth()->user())
            && Capabilities::supports(Capability::Aggregation);
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        abort_unless(self::canView(), 403);

        try {
            $groups = app(ActivityRepositoryInterface::class)->aggregate(
                (new ActivityQuery())->last('7 days'),
                'channel',
            );
        } catch (InvalidArgumentException) {
            $groups = [];
        }

        return [
            'datasets' => [
                ['label' => 'Entries', 'data' => array_values($groups)],
            ],
            'labels' => array_keys($groups),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
