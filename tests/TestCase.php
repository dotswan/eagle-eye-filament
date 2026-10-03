<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Tests;

use Livewire\LivewireServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Filament\FilamentServiceProvider;
use Dotswan\EagleEye\EagleEyeServiceProvider;
use Dotswan\EagleEyeFilament\EagleEyeFilamentServiceProvider;
use Dotswan\EagleEyeFilament\Tests\Support\TestPanelProvider;
use Filament\FilamentManager;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Livewire\Mechanisms\DataStore;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionClass;

abstract class TestCase extends Orchestra
{
    protected $enablesPackageDiscoveries = false;

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            EagleEyeServiceProvider::class,
            EagleEyeFilamentServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('eagle-eye.capture.outbound_http.enabled', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $provider = new ReflectionClass(EagleEyeServiceProvider::class);
        $source = $provider->getFileName();

        if (false !== $source) {
            $this->loadMigrationsFrom(dirname($source, 2).'/database/migrations');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        app()->instance(DataStore::class, new DataStore());

        $errors = new ViewErrorBag();
        $errors->put('default', new MessageBag());
        app('view')->share('errors', $errors);

        app(FilamentManager::class)->setCurrentPanel('testing');
    }
}
