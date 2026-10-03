# Configuration

The plugin works without publishing anything. Its own config only controls the Livewire interaction capture. Everything about recording, storage, redaction and drivers is configured in the core package (`config/eagle-eye.php`).

```bash
php artisan vendor:publish --tag=eagle-eye-filament-config
```

## Reference

```php
return [
    'livewire' => [
        'ignore_methods' => ['$refresh', '__dispatch', 'loadFacets', '$sync', 'poll'],
        'max_components' => 10,
        'max_calls'      => 20,
        'max_updates'    => 20,
    ],
];
```

### Livewire interaction capture

When the core records an `http.request` entry for a Livewire update request, the plugin attaches a `livewire` block to its payload and the `livewire:component:<name>` tag (first three components). The block lists, per component:

- the component name and class,
- the page title (from `getTitle()`, `getHeading()` or `getNavigationLabel()`),
- the names of the called methods,
- the names of the updated properties (never their values),
- the mounted Filament action name and label, if any.

It is also used to build a readable title such as "Delete on Orders" or "Interacted with Settings" (core translation keys `eagle-eye::titles.http.livewire_action` and `eagle-eye::titles.http.livewire`).

Collection runs inside the core's capture suppression, so any database query triggered while resolving a title or label is not recorded as activity. Calls are recorded before the method runs, so calls that end in a validation error or `Halt` are still listed.

| Key | Type | Default | Effect |
|---|---|---|---|
| `livewire.ignore_methods` | `list<string>` | `['$refresh', '__dispatch', 'loadFacets', '$sync', 'poll']` | Method names that are not recorded. A request that only calls ignored methods is marked `quiet: true`. Add your own polling or housekeeping methods here. |
| `livewire.max_components` | `int` | `10` | Maximum components recorded per request. |
| `livewire.max_calls` | `int` | `20` | Maximum method calls recorded per component. |
| `livewire.max_updates` | `int` | `20` | Maximum distinct updated property names recorded per component. |

When a limit is reached, further items are dropped and the block is marked `truncated: true`. Non-positive or non-numeric values fall back to the defaults.

To disable the capture entirely, exclude Livewire update requests from HTTP capture in the core config. The plugin never records anything unless the core records the `http.request` entry.

## Core settings the UI reads

| Core key | Used for |
|---|---|
| `eagle-eye.capture.query.slow_threshold_ms` | The "Slow (≥ N ms)" label on query details. |
| `eagle-eye.drivers.loki.read.default_range_days` | On drivers without unbounded time ranges (Loki), the "first seen" value on record history is shown as approximate when it hits this window. |
| `app.timezone` | Interpreting `from` / `until` dates in the explorer. |

## Environment variables

The plugin defines no environment variables. Use the core's variables (`EAGLE_EYE_DRIVER`, `EAGLE_EYE_SECURITY_KEY`, …).

## Publishing

| Tag | Target |
|---|---|
| `eagle-eye-filament-config` | `config/eagle-eye-filament.php` |
| `eagle-eye-filament-views` | `resources/views/vendor/eagle-eye-filament` |
| `eagle-eye-filament-translations` | `lang/vendor/eagle-eye-filament` |

The stylesheet is published by Filament, not by `vendor:publish`:

```bash
php artisan filament:assets
```
