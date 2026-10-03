<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Filament\Widgets\Overview;

use Dotswan\EagleEye\Contracts\Repository\ActivityRepositoryInterface;
use Dotswan\EagleEye\Contracts\Security\LogAccessPolicyInterface;
use Dotswan\EagleEyeFilament\Filament\Concerns\AuthorizesActivityAccess;
use Dotswan\EagleEyeFilament\Support\Presentation\EntryFormatter;
use Dotswan\EagleEyeFilament\Support\Stats\ActivityRange;
use Filament\Widgets\Widget;

abstract class OverviewSection extends Widget
{
    use AuthorizesActivityAccess;

    public string $range = '24h';

    public function mount(string $range = '24h'): void
    {
        $this->range = $range;
        $this->activityRange();
    }

    public static function canView(): bool
    {
        return app(LogAccessPolicyInterface::class)->canView(auth()->user());
    }

    protected function activityRange(): ActivityRange
    {
        $range = ActivityRange::tryFrom($this->range);

        if (! $range instanceof ActivityRange) {
            abort(422, 'The selected activity range is invalid.');
        }

        return $range;
    }

    protected function repository(): ActivityRepositoryInterface
    {
        return app(ActivityRepositoryInterface::class);
    }

    protected function formatter(): EntryFormatter
    {
        return app(EntryFormatter::class);
    }

    protected function unavailableMessage(): string
    {
        return 'Backend unavailable, try a narrower time range';
    }

    /** @return array{0: ?string, 1: ?string} */
    protected function delta(int $current, int $previous, bool $higherIsBetter = true): array
    {
        if ($previous < 10) {
            return [null, null];
        }

        $change = (($current - $previous) / $previous) * 100;
        $tone = ($change > 0) === $higherIsBetter ? 'good' : 'bad';

        if (abs($change) >= 1000) {
            return [($change > 0 ? '>' : '<').'999%', $tone];
        }

        $rounded = $this->trimNumber($change);

        if ('0' === $rounded) {
            return [null, null];
        }

        return [($change > 0 ? '+' : '').$rounded.'%', $tone];
    }

    /** @return array{0: ?string, 1: ?string} */
    protected function rateDelta(int $errors, int $events, int $previousErrors, int $previousEvents): array
    {
        $rate = $events > 0 ? $errors / $events * 100 : 0.0;
        $previousRate = $previousEvents > 0 ? $previousErrors / $previousEvents * 100 : 0.0;
        $change = $rate - $previousRate;

        if (abs($change) < 0.05) {
            return [null, null];
        }

        return [sprintf('%+.1fpt', $change), $change > 0 ? 'bad' : 'good'];
    }

    protected function trimNumber(float $value): string
    {
        return mb_rtrim(mb_rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }
}
