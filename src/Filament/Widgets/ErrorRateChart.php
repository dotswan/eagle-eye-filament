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

final class ErrorRateChart extends ChartWidget
{
    protected ?string $heading = 'Error share';

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
                'severity',
            );
        } catch (InvalidArgumentException) {
            $groups = [];
        }

        $errors = ($groups['error'] ?? 0) + ($groups['critical'] ?? 0)
            + ($groups['alert'] ?? 0) + ($groups['emergency'] ?? 0);
        $total = array_sum($groups);
        $rest = max(0, $total - $errors);

        return [
            'datasets' => [
                ['label' => 'Entries', 'data' => [$errors, $rest]],
            ],
            'labels' => ['errors', 'other'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
