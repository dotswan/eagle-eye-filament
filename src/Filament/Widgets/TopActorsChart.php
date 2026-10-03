<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Filament\Widgets;

use Dotswan\EagleEye\Contracts\Security\LogAccessPolicyInterface;
use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Enums\Capability;
use Dotswan\EagleEyeFilament\Support\Capabilities;
use Dotswan\EagleEyeFilament\Support\Stats\ActorRanking;
use Filament\Widgets\ChartWidget;
use LengthException;

/**
 * Complete actor ranking over the bounded dashboard window.
 */
final class TopActorsChart extends ChartWidget
{
    protected ?string $heading = 'Top actors (last 7 days)';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return app(LogAccessPolicyInterface::class)->canView(auth()->user())
            && Capabilities::supports(Capability::RankedAggregation);
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        abort_unless(self::canView(), 403);

        try {
            $ranking = app(ActorRanking::class)->top((new ActivityQuery())->last('7 days'), 5);
        } catch (LengthException) {
            $this->description = 'Actor ranking unavailable because the selected backend cannot prove an exact cutoff.';

            return [
                'datasets' => [['label' => 'Entries', 'data' => []]],
                'labels'   => [],
            ];
        }

        $this->description = null;

        return [
            'datasets' => [
                ['label' => 'Entries', 'data' => array_map(static fn (array $row): int => $row['events'], $ranking)],
            ],
            'labels' => array_map(
                static fn (array $row): string => $row['actor']->label ?? $row['actor']->type.':'.($row['actor']->id ?? ''),
                $ranking,
            ),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
