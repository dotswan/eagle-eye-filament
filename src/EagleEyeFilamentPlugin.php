<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament;

use Dotswan\EagleEyeFilament\Filament\Pages\ActivityOverviewPage;
use Dotswan\EagleEyeFilament\Filament\Pages\RecentTracesPage;
use Dotswan\EagleEyeFilament\Filament\Pages\SubjectHistoryPage;
use Dotswan\EagleEyeFilament\Filament\Pages\TraceTimelinePage;
use Dotswan\EagleEyeFilament\Filament\Pages\UserActivityPage;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource;
use Dotswan\EagleEyeFilament\Filament\Widgets\ActivityTimelineWidget;
use Dotswan\EagleEyeFilament\Filament\Widgets\ActivityVolumeChart;
use Dotswan\EagleEyeFilament\Filament\Widgets\ErrorRateChart;
use Dotswan\EagleEyeFilament\Filament\Widgets\TopActorsChart;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use UnitEnum;

final class EagleEyeFilamentPlugin implements Plugin
{
    /** @var array<class-string<Widget>|WidgetConfiguration> */
    private array $widgets = [
        ActivityTimelineWidget::class,
        ActivityVolumeChart::class,
        TopActorsChart::class,
        ErrorRateChart::class,
    ];

    private bool $registersWidgets = true;

    private string|UnitEnum|null $navigationGroup = null;

    private ?int $navigationSort = null;

    public static function make(): static
    {
        return new self();
    }

    /**
     * Returns the plugin registered on the current (or default) panel, if any.
     */
    public static function current(): ?self
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        if (! $panel instanceof Panel || ! $panel->hasPlugin('eagle-eye')) {
            return null;
        }

        $plugin = $panel->getPlugin('eagle-eye');

        return $plugin instanceof self ? $plugin : null;
    }

    public function getId(): string
    {
        return 'eagle-eye';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([ActivityLogResource::class])
            ->pages([ActivityOverviewPage::class, TraceTimelinePage::class, UserActivityPage::class, RecentTracesPage::class, SubjectHistoryPage::class]);

        if ($this->registersWidgets) {
            $panel->widgets($this->widgets);
        }
    }

    public function boot(Panel $panel): void
    {
    }

    /**
     * @param  array<class-string<Widget>|WidgetConfiguration>  $widgets
     */
    public function widgets(array $widgets): static
    {
        $this->widgets = $widgets;

        return $this;
    }

    public function withoutWidgets(bool $condition = true): static
    {
        $this->registersWidgets = ! $condition;

        return $this;
    }

    /**
     * Places the "Activity overview" and "Activity Log" sidebar items in a navigation group.
     */
    public function navigationGroup(string|UnitEnum|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        return $this->navigationGroup;
    }

    /**
     * Sort position of "Activity overview"; "Activity Log" is placed directly after it.
     */
    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }
}
