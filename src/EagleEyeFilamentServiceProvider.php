<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament;

use Closure;
use Dotswan\EagleEye\Capture\CaptureSuppression;
use Dotswan\EagleEyeFilament\Filament\Livewire\ExplorerPane;
use Dotswan\EagleEyeFilament\Support\Livewire\LivewireInteractionCollector;
use Dotswan\EagleEyeFilament\Support\Livewire\LivewireRequestEnricher;
use Dotswan\EagleEyeFilament\Support\Title\FilamentHttpTitleResolver;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Throwable;

final class EagleEyeFilamentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/eagle-eye-filament.php', 'eagle-eye-filament');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'eagle-eye-filament');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'eagle-eye-filament');

        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'eagle-eye-filament');

        Livewire::component('ee-explorer-pane', ExplorerPane::class);

        $this->app->scoped(LivewireInteractionCollector::class);
        $this->app->scoped(LivewireRequestEnricher::class);
        $this->app->tag([LivewireRequestEnricher::class], 'eagle-eye.enrichers');
        $this->app->tag([FilamentHttpTitleResolver::class], 'eagle-eye.title_resolvers');

        $this->registerLivewireHooks();

        FilamentAsset::register([
            Css::make('eagle-eye-filament', __DIR__.'/../resources/dist/eagle-eye-filament.css'),
        ], package: 'dotswan/eagle-eye-filament');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/eagle-eye-filament.php' => config_path('eagle-eye-filament.php'),
            ], 'eagle-eye-filament-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/eagle-eye-filament'),
            ], 'eagle-eye-filament-views');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/eagle-eye-filament'),
            ], 'eagle-eye-filament-translations');
        }
    }

    private function registerLivewireHooks(): void
    {
        try {
            Livewire::listen('call', static function (mixed $component = null, mixed $method = null, mixed $params = []): Closure {
                app(CaptureSuppression::class)->run(static function () use ($component, $method, $params): void {
                    app(LivewireInteractionCollector::class)->recordCall($component, $method, $params);
                });

                return static function () use ($component, $method, $params): void {
                    app(CaptureSuppression::class)->run(static function () use ($component, $method, $params): void {
                        app(LivewireInteractionCollector::class)->refreshAction($component, $method, $params);
                    });
                };
            });

            Livewire::listen('update', static function (mixed $component = null, mixed $property = null): void {
                app(CaptureSuppression::class)->run(static function () use ($component, $property): void {
                    app(LivewireInteractionCollector::class)->recordUpdate($component, $property);
                });
            });
        } catch (Throwable) {
        }
    }
}
