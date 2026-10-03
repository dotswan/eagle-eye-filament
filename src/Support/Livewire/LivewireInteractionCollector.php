<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Support\Livewire;

use Throwable;

final class LivewireInteractionCollector
{
    /** @var array<string, array{name: string, class: string, page: string|null, calls: list<string>, updates: list<string>, action: array{name: string, label: string}|null}> */
    private array $components = [];

    private bool $truncated = false;

    public function recordCall(mixed $component, mixed $method, mixed $params = []): void
    {
        if (! is_object($component) || ! is_string($method) || '' === $method) {
            return;
        }

        $key = $this->ensure($component);

        if (null === $key || $this->ignored($method)) {
            return;
        }

        $entry = $this->components[$key];

        if (count($entry['calls']) >= $this->max('max_calls', 20)) {
            $this->truncated = true;

            return;
        }

        $entry['calls'][] = $method;

        if (in_array($method, ['mountAction', 'callMountedAction'], true)) {
            $action = $this->resolveAction($component, $method, is_array($params) ? $params : []);

            if (null !== $action) {
                $entry['action'] = $action;
            }
        }

        $this->components[$key] = $entry;
    }

    /**
     * Re-resolves the action label once the call has finished, because `mountAction`
     * only mounts its action (and therefore knows its label) after the method ran.
     */
    public function refreshAction(mixed $component, mixed $method, mixed $params = []): void
    {
        if (! is_object($component) || 'mountAction' !== $method) {
            return;
        }

        $key = $component::class.'#'.spl_object_id($component);

        if (! isset($this->components[$key])) {
            return;
        }

        $action = $this->resolveAction($component, $method, is_array($params) ? $params : []);

        if (null !== $action) {
            $this->components[$key]['action'] = $action;
        }
    }

    public function recordUpdate(mixed $component, mixed $property): void
    {
        if (! is_object($component) || ! is_string($property) || '' === $property) {
            return;
        }

        $key = $this->ensure($component);

        if (null === $key) {
            return;
        }

        $entry = $this->components[$key];

        if (count($entry['updates']) >= $this->max('max_updates', 20)) {
            $this->truncated = true;

            return;
        }

        if (! in_array($property, $entry['updates'], true)) {
            $entry['updates'][] = $property;
            $this->components[$key] = $entry;
        }
    }

    /**
     * @return array{components: list<array{name: string, class: string, page: string|null, calls: list<string>, updates: list<string>, action: array{name: string, label: string}|null}>, quiet: bool, truncated: bool}|null
     */
    public function snapshot(): ?array
    {
        if ([] === $this->components) {
            return null;
        }

        $components = array_values($this->components);
        $quiet = true;

        foreach ($components as $component) {
            if ([] !== $component['calls'] || [] !== $component['updates'] || null !== $component['action']) {
                $quiet = false;
                break;
            }
        }

        return [
            'components' => $components,
            'quiet'      => $quiet,
            'truncated'  => $this->truncated,
        ];
    }

    public function reset(): void
    {
        $this->components = [];
        $this->truncated = false;
    }

    private function ensure(object $component): ?string
    {
        $key = $component::class.'#'.spl_object_id($component);

        if (isset($this->components[$key])) {
            return $key;
        }

        if (count($this->components) >= $this->max('max_components', 10)) {
            $this->truncated = true;

            return null;
        }

        $this->components[$key] = [
            'name'    => $this->name($component),
            'class'   => $component::class,
            'page'    => $this->page($component),
            'calls'   => [],
            'updates' => [],
            'action'  => null,
        ];

        return $key;
    }

    private function name(object $component): string
    {
        if (method_exists($component, 'getName')) {
            try {
                $name = $component->getName();

                if (is_string($name) && '' !== $name) {
                    return $name;
                }
            } catch (Throwable) {
            }
        }

        return class_basename($component);
    }

    private function page(object $component): ?string
    {
        foreach (['getTitle', 'getHeading', 'getNavigationLabel'] as $method) {
            if (! method_exists($component, $method)) {
                continue;
            }

            try {
                $value = $component->{$method}();

                if (is_string($value) && '' !== $value) {
                    return $value;
                }
            } catch (Throwable) {
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $params
     * @return array{name: string, label: string}|null
     */
    private function resolveAction(object $component, string $method, array $params): ?array
    {
        $name = 'mountAction' === $method && isset($params[0]) && is_string($params[0])
            ? $params[0]
            : $this->mountedActionName($component);

        if (null === $name || '' === $name) {
            return null;
        }

        $label = $name;
        $mounted = $this->mountedAction($component);

        if (is_object($mounted) && method_exists($mounted, 'getLabel') && $this->mountedNameMatches($mounted, $name)) {
            try {
                $resolved = $mounted->getLabel();

                if (is_string($resolved) && '' !== $resolved) {
                    $label = $resolved;
                }
            } catch (Throwable) {
            }
        }

        return ['name' => $name, 'label' => $label];
    }

    private function mountedNameMatches(object $mounted, string $name): bool
    {
        if (! method_exists($mounted, 'getName')) {
            return true;
        }

        try {
            return $mounted->getName() === $name;
        } catch (Throwable) {
            return false;
        }
    }

    private function mountedActionName(object $component): ?string
    {
        $mounted = $this->mountedAction($component);

        if (is_object($mounted) && method_exists($mounted, 'getName')) {
            $name = $mounted->getName();

            if (is_string($name) && '' !== $name) {
                return $name;
            }
        }

        if (! property_exists($component, 'mountedActions')) {
            return null;
        }

        $actions = $component->mountedActions ?? [];

        if (! is_array($actions) || [] === $actions) {
            return null;
        }

        $last = end($actions);

        if (is_array($last) && isset($last['name']) && is_string($last['name']) && '' !== $last['name']) {
            return $last['name'];
        }

        return null;
    }

    private function mountedAction(object $component): ?object
    {
        if (! method_exists($component, 'getMountedAction')) {
            return null;
        }

        try {
            $mounted = $component->getMountedAction();

            return is_object($mounted) ? $mounted : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function ignored(string $method): bool
    {
        $ignore = config('eagle-eye-filament.livewire.ignore_methods', []);
        $ignore = is_array($ignore) ? $ignore : [];

        return in_array($method, $ignore, true);
    }

    private function max(string $key, int $default): int
    {
        $value = config('eagle-eye-filament.livewire.'.$key, $default);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
    }
}
