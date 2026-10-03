<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Tests\Support;

use Filament\Pages\Page;

final class TestSettingsPage extends Page
{
    protected static ?string $slug = 'test-settings';

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationLabel(): string
    {
        return 'Settings';
    }
}
