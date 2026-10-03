<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Tests\Support;

use Dotswan\EagleEyeFilament\EagleEyeFilamentPlugin;
use Filament\Panel;
use Filament\PanelProvider;

final class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('testing')
            ->path('/')
            ->pages([TestSettingsPage::class])
            ->plugin(EagleEyeFilamentPlugin::make());
    }
}
