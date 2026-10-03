# Eagle Eye for Filament

[![Latest Version on Packagist](https://img.shields.io/packagist/v/dotswan/eagle-eye-filament.svg?style=flat-square)](https://packagist.org/packages/dotswan/eagle-eye-filament)
[![CI](https://img.shields.io/github/actions/workflow/status/dotswan/eagle-eye-filament/ci.yml?branch=main&label=CI&style=flat-square)](https://github.com/dotswan/eagle-eye-filament/actions/workflows/ci.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/dotswan/eagle-eye-filament.svg?style=flat-square)](https://packagist.org/packages/dotswan/eagle-eye-filament)
[![License](https://img.shields.io/packagist/l/dotswan/eagle-eye-filament.svg?style=flat-square)](LICENSE.md)

A read-only Filament 5 admin UI for [Eagle Eye](https://github.com/dotswan/eagle-eye), the driver-agnostic activity log for Laravel.

The plugin adds an activity overview dashboard, an activity explorer, event detail pages, trace timelines, actor and record history, and a set of dashboard widgets. Everything is read through Eagle Eye's repository contracts, so the same UI works on the database, Loki, memory and composite (`multi`) drivers. There is no Eloquent activity model and nothing is ever written back.

## Features

- **Activity overview** with KPI cards, sparklines, events by channel, severity mix, failing traces, latest requests and busiest actors.
- **Activity explorer** with facet counts, token search (`channel:http severity:>=error actor:user:42`), a *Stream* and a *By trace* view, an inline detail pane and shareable URLs.
- **Event detail** with per-channel sections (request, mail, job, query, exception, …), field-level changes including changed paths inside JSON columns, the payload, related entries and policy-gated reveal of sealed values.
- **Trace timeline** (causal waterfall), **Recent traces**, **Actor activity** (stats, 7 × 24 heatmap, top actions) and **Record history** (version timeline per model).
- **Dashboard widgets**: activity timeline, volume by channel, top actors and error share.
- **Readable Filament request titles**: Livewire requests are recorded as "Delete on Orders" instead of `POST /livewire/update`.
- Authorization on every request, bounded reads, capability-aware features, light and dark mode, no custom theme or build step.

## Requirements

| Dependency | Version |
|---|---|
| PHP | 8.4 or newer |
| Laravel | 11 (11.28+) or 12 |
| Filament | 5.x |
| [`dotswan/eagle-eye`](https://github.com/dotswan/eagle-eye) | ^1.0 |

## Installation

### 1. Install and configure the core

The plugin only displays what `dotswan/eagle-eye` records. Install the core first and follow its [installation guide](https://github.com/dotswan/eagle-eye#installation): publish its config and migrations, run the migrations, set `EAGLE_EYE_SECURITY_KEY` and choose a driver.

```bash
composer require dotswan/eagle-eye
php artisan vendor:publish --tag=eagle-eye-config
php artisan vendor:publish --tag=eagle-eye-migrations
php artisan migrate
```

### 2. Install the plugin

```bash
composer require dotswan/eagle-eye-filament
```

The service provider is registered through Laravel package discovery.

### 3. Register the plugin on a panel

```php
use Dotswan\EagleEyeFilament\EagleEyeFilamentPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(EagleEyeFilamentPlugin::make());
}
```

### 4. Publish the stylesheet

```bash
php artisan filament:assets
```

This copies the plugin stylesheet to `public/css/dotswan/eagle-eye-filament/`. Run it again after every update of the plugin (adding it to the `post-autoload-dump` script in your `composer.json` is common for Filament apps). A custom Filament theme, Tailwind or Vite are **not** required: the stylesheet is plain CSS and uses your panel's colours.

### 5. Grant access

Nobody can see the pages until access is granted. By default the core checks two Gate abilities:

```php
use Dotswan\EagleEye\Data\ActivityEntry;
use Illuminate\Support\Facades\Gate;

// In a service provider's boot() method:
Gate::define('viewActivityLog', fn ($user) => $user->isAdmin());
Gate::define('revealActivityLog', fn ($user, ActivityEntry $entry, string $path) => $user->isSuperAdmin());
```

See [Authorization](docs/authorization.md) for custom policies.

## Plugin options

```php
EagleEyeFilamentPlugin::make()
    ->navigationGroup('Monitoring')
    ->navigationSort(90)
    ->widgets([ActivityVolumeChart::class, ErrorRateChart::class])
    ->withoutWidgets(false);
```

| Method | Default | Description |
|---|---|---|
| `navigationGroup(string\|UnitEnum\|null $group)` | `null` (ungrouped) | Navigation group for the two sidebar items. |
| `navigationSort(?int $sort)` | `null` (overview `-1`, log `0`) | Sort position of *Activity overview*; *Activity Log* is placed at `$sort + 1`. |
| `widgets(array $widgets)` | all four widgets | Dashboard widgets registered on the panel. Accepts widget classes or `Widget::make([...])` configurations. |
| `withoutWidgets(bool $condition = true)` | widgets are registered | Registers no dashboard widgets at all. |

The plugin ID is `eagle-eye`, so `$panel->getPlugin('eagle-eye')` returns the instance. Details and examples are in [Customization](docs/customization.md).

## Pages

The plugin adds two sidebar items. Every other page is a drill-down linked from the content.

| Page | Route (relative to the panel path) | Sidebar | Parameters |
|---|---|---|---|
| Activity overview | `activity-overview` | Yes, only if the driver supports aggregation | `range` (`1h`, `24h`, `7d`, `30d`) |
| Activity Log (explorer) | `activity-logs` | Yes | `channel[]`, `severity[]`, `actions[]`, `q`, `actorType`, `actorId`, `subjectType`, `subjectId`, `trace`, `range` (`1h`, `24h` default, `7d`, `30d`, `custom`), `from`, `until` (`YYYY-MM-DD`), `per_page` (25, 50, 100), `variant` (`stream`, `trace`), `selected` |
| Event detail | `activity-logs/{record}` | No | – |
| Trace timeline | `trace-timeline-page` | No | `trace` (32 lowercase hex characters) |
| Recent traces | `activity-traces` | No | – |
| Actor activity | `user-activity-page` | No | `actor_type`, `actor_id` |
| Record history | `activity-subject` | No | `subject_type`, `subject_id` |

Route names follow Filament's conventions, for example `filament.admin.resources.activity-logs.index` and `filament.admin.pages.activity-overview`. In code, prefer the class helpers:

```php
use Dotswan\EagleEyeFilament\Filament\Pages\SubjectHistoryPage;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource;

ActivityLogResource::getUrl('index', ['subjectType' => 'order', 'subjectId' => '42']);
SubjectHistoryPage::getUrl(['subject_type' => 'order', 'subject_id' => '42']);
```

## Widgets

| Widget | Shows | Needs driver capability |
|---|---|---|
| `ActivityTimelineWidget` | Latest entries (default 10, max 100), optionally for one subject | – |
| `ActivityVolumeChart` | Entries by channel, last 7 days | `Aggregation` |
| `TopActorsChart` | Top 5 actors, last 7 days | `RankedAggregation` |
| `ErrorRateChart` | Error share, last 7 days | `Aggregation` |

All widgets live in `Dotswan\EagleEyeFilament\Filament\Widgets`. They are hidden when the viewer is not authorized or the driver lacks the capability, and they do not poll. To show a record's history on one of your own resource pages:

```php
use Dotswan\EagleEyeFilament\Filament\Widgets\ActivityTimelineWidget;

protected function getFooterWidgets(): array
{
    return [
        ActivityTimelineWidget::make([
            'subjectType' => 'order',          // the subject type recorded by the core
            'subjectId'   => (string) $this->record->getKey(),
            'limit'       => 10,
        ]),
    ];
}
```

## Configuration

Publishing the config is optional:

```bash
php artisan vendor:publish --tag=eagle-eye-filament-config
```

```php
// config/eagle-eye-filament.php
return [
    'livewire' => [
        'ignore_methods' => ['$refresh', '__dispatch', 'loadFacets', '$sync', 'poll'],
        'max_components' => 10,
        'max_calls'      => 20,
        'max_updates'    => 20,
    ],
];
```

These keys control the Livewire interaction capture that enriches the core's `http.request` entries. See [Configuration](docs/configuration.md) for each key. The plugin reads no environment variables of its own; display and storage behaviour (drivers, redaction, slow-query threshold, Loki read window) come from the core config.

## Publishing

| Tag | Publishes to | Purpose |
|---|---|---|
| `eagle-eye-filament-config` | `config/eagle-eye-filament.php` | Livewire capture settings |
| `eagle-eye-filament-views` | `resources/views/vendor/eagle-eye-filament` | Override pages, partials, widgets and components |
| `eagle-eye-filament-translations` | `lang/vendor/eagle-eye-filament` | Override the event detail labels |

```bash
php artisan vendor:publish --tag=eagle-eye-filament-views
```

Only publish the views you need to change: published views are not updated with the plugin. See [Customization](docs/customization.md).

## Performance

- Every read is bounded: 25 to 100 rows per explorer page, 20 rows on the other lists, 50 entries per trace, top-8 action facets, top-5 actor rankings.
- Pages that need a capability the driver lacks (aggregation, series aggregation, full-text search, exact counts, ranked aggregation) hide that feature or show an "unavailable" state instead of falling back to expensive queries.
- The overview page renders an empty shell. Its four sections load lazily and cost about ten aggregate or search calls in total per range.
- Explorer facet counts and totals are cached for 60 seconds per viewer and filter (default cache store).
- Nothing polls. Charts and lists refresh on navigation.
- On Loki, keep the explorer window short (the default is 24 hours) and prefer the `multi` driver with the SQL index for listings and counts. See the core's Loki and performance documentation.

More detail: [Architecture and performance](docs/architecture.md).

## Possible conflicts

The plugin registers these names. Avoid reusing them in the same panel or application:

| Kind | Name |
|---|---|
| Plugin ID | `eagle-eye` |
| Resource slug | `activity-logs` |
| Page slugs | `activity-overview`, `activity-traces`, `activity-subject`, `trace-timeline-page`, `user-activity-page` |
| View and translation namespace | `eagle-eye-filament` |
| Blade components | `x-eagle-eye-filament::*` |
| Livewire component alias | `ee-explorer-pane` |
| Filament asset | `eagle-eye-filament` (package `dotswan/eagle-eye-filament`) |
| CSS | classes prefixed `ee-`, scoped under `.ee` |
| Cache keys | `eagle-eye:facets:*` |
| Container tags used | `eagle-eye.enrichers`, `eagle-eye.title_resolvers` (core tags) |

## Troubleshooting

| Symptom | Fix |
|---|---|
| Pages look unstyled | Run `php artisan filament:assets` and clear the browser cache. Re-run it after every plugin update. |
| Sidebar items are missing or pages return 403 | The viewer is not authorized. Define the `viewActivityLog` ability or bind your own `LogAccessPolicyInterface`. See [Authorization](docs/authorization.md). |
| *Activity overview* is missing but *Activity Log* is visible | The active driver does not support `Aggregation` (for example the `null` driver). |
| Explorer is empty | Check the time window (default: last 24 hours) and that the core is recording (`php artisan activity-log:doctor`). |
| "Reveal" is refused | Define the `revealActivityLog` ability; it receives the entry and the field path. |
| Search box is disabled | The driver does not support full-text search. Token filters such as `channel:` still work. |
| Published view breaks after an update | Delete the published copy, re-publish and re-apply your change. |

## Testing

```bash
composer test     # Pest
composer check    # Rector, Pint, PHPStan and Pest
```

## Documentation

- [Configuration](docs/configuration.md)
- [Authorization](docs/authorization.md)
- [Customization](docs/customization.md)
- [Architecture and performance](docs/architecture.md)
- [Eagle Eye core documentation](https://github.com/dotswan/eagle-eye)

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Security

Please report vulnerabilities to tech@dotswan.com. See [SECURITY.md](SECURITY.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
