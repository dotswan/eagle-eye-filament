<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Filament\Pages;

use BackedEnum;
use Dotswan\EagleEye\Contracts\Security\LogAccessPolicyInterface;
use Dotswan\EagleEye\Enums\Capability;
use Dotswan\EagleEyeFilament\EagleEyeFilamentPlugin;
use Dotswan\EagleEyeFilament\Filament\Concerns\AuthorizesActivityAccess;
use Dotswan\EagleEyeFilament\Filament\Concerns\HasFullContentWidth;
use Dotswan\EagleEyeFilament\Support\Capabilities;
use Dotswan\EagleEyeFilament\Support\Presentation\ActivityNav;
use Dotswan\EagleEyeFilament\Support\Stats\ActivityRange;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use UnitEnum;

final class ActivityOverviewPage extends Page
{
    use AuthorizesActivityAccess;
    use HasFullContentWidth;

    protected static ?string $slug = 'activity-overview';

    protected static ?string $navigationLabel = 'Activity overview';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = -1;

    protected string $view = 'eagle-eye-filament::pages.activity-overview';

    #[Url]
    public string $range = '24h';

    public static function canAccess(): bool
    {
        return Capabilities::supports(Capability::Aggregation)
            && app(LogAccessPolicyInterface::class)->canView(auth()->user());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return EagleEyeFilamentPlugin::current()?->getNavigationGroup() ?? parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return EagleEyeFilamentPlugin::current()?->getNavigationSort() ?? parent::getNavigationSort();
    }

    public function render(): View
    {
        $this->authorizeAccess();
        $this->getRange();

        return parent::render();
    }

    public function getTitle(): string
    {
        $this->authorizeAccess();

        return 'Activity overview';
    }

    public function getHeading(): ?string
    {
        $this->authorizeAccess();

        return null;
    }

    /** @return list<array{key: string, label: string, href: string, tone: string, badge: ?string}> */
    public function getNavItems(): array
    {
        $this->authorizeAccess();

        return ActivityNav::items();
    }

    public function getRange(): ActivityRange
    {
        $this->authorizeAccess();
        $range = ActivityRange::tryFrom($this->range);

        if (! $range instanceof ActivityRange) {
            abort(422, 'The selected activity range is invalid.');
        }

        return $range;
    }

    /** @return list<array{label: string, active: bool, href: string, tone: string, title: ?string}> */
    public function getRangeOptions(): array
    {
        $this->authorizeAccess();
        $current = $this->getRange();
        $options = [];

        foreach (ActivityRange::cases() as $range) {
            $options[] = [
                'label'  => $range->value,
                'active' => $range === $current,
                'href'   => request()->fullUrlWithQuery(['range' => $range->value]),
                'tone'   => 'blue',
                'title'  => null,
            ];
        }

        return $options;
    }
}
