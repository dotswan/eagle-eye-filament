<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Support\Title;

use Dotswan\EagleEye\Contracts\Title\TemplateRendererInterface;
use Dotswan\EagleEye\Contracts\Title\TitleResolverInterface;
use Dotswan\EagleEye\Data\ActivityDraft;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEyeFilament\Support\Presentation\EntryFormatter;
use Filament\Facades\Filament;
use Filament\Panel;
use Throwable;

final class FilamentHttpTitleResolver implements TitleResolverInterface
{
    /** @var array<string, array{kind: string, label: string, plural: string}>|null */
    private static ?array $routes = null;

    public function __construct(
        private readonly TemplateRendererInterface $renderer,
        private readonly EntryFormatter $formatter,
    ) {
    }

    /** @internal */
    public static function resetRouteMap(): void
    {
        self::$routes = null;
    }

    public function resolve(ActivityDraft $draft): ?string
    {
        if (ActivityChannel::Http !== $draft->channel) {
            return null;
        }

        $route = $draft->request?->route;

        if (! is_string($route) || '' === $route) {
            return null;
        }

        if ($this->isLivewireUpdate($route)) {
            return $this->livewireTitle($draft);
        }

        $entry = $this->routeMap()[$route] ?? null;

        if (null === $entry) {
            return null;
        }

        $params = 'page' === $entry['kind']
            ? ['page' => $entry['label']]
            : [
                'resource_label' => 'index' === $entry['kind'] ? $entry['plural'] : $entry['label'],
                'record'         => $this->recordKey($draft),
            ];

        $key = match ($entry['kind']) {
            'index'  => 'http.resource.index',
            'create' => 'http.resource.create',
            'view'   => 'http.resource.view',
            'edit'   => 'http.resource.edit',
            default  => 'http.page',
        };

        return $this->render($draft, $key, $params);
    }

    /** @param array<string, mixed> $params */
    private function render(ActivityDraft $draft, string $key, array $params): string
    {
        $draft->payload['title_source'] = ['key' => 'titles.'.$key, 'params' => $params];

        return $this->renderer->render((string) __('eagle-eye::titles.'.$key), $params);
    }

    private function livewireTitle(ActivityDraft $draft): string
    {
        $components = $draft->payload['livewire']['components'] ?? [];
        $components = is_array($components) ? $components : [];
        $page = null;
        $action = null;

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            $componentPage = $component['page'] ?? null;

            if (null === $page && is_string($componentPage) && '' !== $componentPage) {
                $page = $componentPage;
            }

            $componentAction = $component['action']['label'] ?? null;

            if (null === $action && is_string($componentAction) && '' !== $componentAction) {
                $action = $componentAction;

                if (is_string($componentPage) && '' !== $componentPage) {
                    $page = $componentPage;
                }
            }
        }

        $page ??= (string) __('eagle-eye-filament::detail.title.this_page');

        if (null !== $action) {
            return $this->render($draft, 'http.livewire_action', ['action_label' => $action, 'page' => $page]);
        }

        return $this->render($draft, 'http.livewire', ['page' => $page]);
    }

    private function isLivewireUpdate(string $route): bool
    {
        return str_contains($route, 'livewire') && str_ends_with($route, '.update');
    }

    private function recordKey(ActivityDraft $draft): string
    {
        $params = $draft->payload['request']['route_params'] ?? null;

        if (! is_array($params)) {
            return '';
        }

        $record = $params['record'] ?? null;

        if (! is_string($record) && ! is_int($record)) {
            return '';
        }

        $key = $this->formatter->shortKey((string) $record);

        return '' === $key ? '' : '#'.$key;
    }

    /** @return array<string, array{kind: string, label: string, plural: string}> */
    private function routeMap(): array
    {
        if (null !== self::$routes) {
            return self::$routes;
        }

        $map = [];

        try {
            $panels = Filament::getPanels();
        } catch (Throwable) {
            return self::$routes = [];
        }

        foreach ($panels as $panel) {
            $map = [...$map, ...$this->mapResources($panel), ...$this->mapPages($panel)];
        }

        return self::$routes = $map;
    }

    /** @return array<string, array{kind: string, label: string, plural: string}> */
    private function mapResources(Panel $panel): array
    {
        $map = [];

        foreach ($panel->getResources() as $resource) {
            if (! class_exists($resource) || ! method_exists($resource, 'getRouteBaseName')) {
                continue;
            }

            try {
                $base = $resource::getRouteBaseName($panel);
                $label = (string) $resource::getModelLabel();
                $plural = method_exists($resource, 'getPluralModelLabel') ? (string) $resource::getPluralModelLabel() : $label;
                $pageKeys = method_exists($resource, 'getPages') ? array_keys($resource::getPages()) : ['index', 'create', 'view', 'edit'];
            } catch (Throwable) {
                continue;
            }

            foreach ($pageKeys as $pageKey) {
                $kind = in_array($pageKey, ['index', 'create', 'view', 'edit'], true) ? $pageKey : 'page';

                $map[$base.'.'.$pageKey] = [
                    'kind'   => $kind,
                    'label'  => 'index' === $kind ? $plural : $label,
                    'plural' => $plural,
                ];
            }
        }

        return $map;
    }

    /** @return array<string, array{kind: string, label: string, plural: string}> */
    private function mapPages(Panel $panel): array
    {
        $map = [];

        foreach ($panel->getPages() as $page) {
            if (! class_exists($page) || ! method_exists($page, 'getSlug')) {
                continue;
            }

            try {
                $slug = $page::getSlug($panel);
                $label = method_exists($page, 'getNavigationLabel') ? (string) $page::getNavigationLabel() : class_basename($page);
            } catch (Throwable) {
                continue;
            }

            if (! is_string($slug) || '' === $slug) {
                continue;
            }

            $name = 'filament.'.$panel->getId().'.pages.'.str_replace('/', '.', $slug);
            $map[$name] = ['kind' => 'page', 'label' => $label, 'plural' => $label];
        }

        return $map;
    }
}
