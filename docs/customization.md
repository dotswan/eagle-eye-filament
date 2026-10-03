# Customization

## Plugin API

```php
use Dotswan\EagleEyeFilament\EagleEyeFilamentPlugin;
use Dotswan\EagleEyeFilament\Filament\Widgets\ActivityTimelineWidget;
use Dotswan\EagleEyeFilament\Filament\Widgets\ErrorRateChart;

$panel->plugin(
    EagleEyeFilamentPlugin::make()
        ->navigationGroup('Monitoring')
        ->navigationSort(90)
        ->widgets([
            ActivityTimelineWidget::make(['limit' => 5]),
            ErrorRateChart::class,
        ]),
);
```

| Method | Default | Notes |
|---|---|---|
| `make()` | – | Creates the plugin. |
| `navigationGroup(string\|UnitEnum\|null $group)` | `null` | Applies to *Activity overview* and *Activity Log*. All other pages are not in the sidebar. |
| `navigationSort(?int $sort)` | `null` | *Activity overview* gets `$sort`, *Activity Log* `$sort + 1`. With `null` the defaults are `-1` and `0`. |
| `widgets(array $widgets)` | `ActivityTimelineWidget`, `ActivityVolumeChart`, `TopActorsChart`, `ErrorRateChart` | Replaces the list of dashboard widgets registered on the panel. Accepts classes and `WidgetConfiguration` objects. |
| `withoutWidgets(bool $condition = true)` | `false` | Registers no dashboard widgets. You can still place the widgets on your own pages. |
| `current()` (static) | – | Returns the plugin instance of the current panel, or `null`. |
| `getId()` | `eagle-eye` | Use with `$panel->getPlugin('eagle-eye')`. |

The two sidebar labels are "Activity overview" (icon `heroicon-o-chart-bar`) and "Activity Log" (icon `heroicon-o-eye`). Use `navigationGroup()` and `navigationSort()` to place them; labels and icons are not configurable in 1.0.

## Widgets

The widgets can be used anywhere Filament accepts widgets (dashboard, resource pages, custom pages), whether or not they are registered through the plugin.

| Widget | Properties |
|---|---|
| `ActivityTimelineWidget` | `subjectType`, `subjectId` (both or neither; locked after mount), `limit` (1–100, default 10) |
| `ActivityVolumeChart` | – (last 7 days, by channel) |
| `TopActorsChart` | – (last 7 days, top 5) |
| `ErrorRateChart` | – (last 7 days, error vs. other) |

```php
ActivityTimelineWidget::make([
    'subjectType' => 'invoice',
    'subjectId'   => (string) $record->getKey(),
]);
```

`subjectType` is the type the core records for the model: the value of the `#[ActivitySubject]` attribute or, by default, the snake-cased class name (`App\Models\OrderLine` → `order_line`). The widget links to the full record history.

The overview sections (`Filament\Widgets\Overview\*`) are internal to the overview page and are not meant to be placed elsewhere.

## Linking to the pages

```php
use Dotswan\EagleEyeFilament\Filament\Pages\SubjectHistoryPage;
use Dotswan\EagleEyeFilament\Filament\Pages\TraceTimelinePage;
use Dotswan\EagleEyeFilament\Filament\Pages\UserActivityPage;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource;

ActivityLogResource::getUrl('index', ['actorType' => 'user', 'actorId' => '42', 'range' => '7d']);
ActivityLogResource::getUrl('view', ['record' => $entryId]);
TraceTimelinePage::getUrl(['trace' => $traceId]);
UserActivityPage::getUrl(['actor_type' => 'user', 'actor_id' => '42']);
SubjectHistoryPage::getUrl(['subject_type' => 'invoice', 'subject_id' => '17']);
```

For example, a "History" action on your own resource:

```php
use Filament\Actions\Action;

Action::make('history')
    ->url(fn ($record): string => SubjectHistoryPage::getUrl([
        'subject_type' => 'invoice',
        'subject_id'   => (string) $record->getKey(),
    ]));
```

## Views

```bash
php artisan vendor:publish --tag=eagle-eye-filament-views
```

Views are published to `resources/views/vendor/eagle-eye-filament` and take precedence over the package views, including the anonymous Blade components (`x-eagle-eye-filament::*`).

| Directory | Contents |
|---|---|
| `pages/` | `activity-overview`, `explorer`, `activity-detail`, `trace-timeline`, `recent-traces`, `actor-profile`, `subject-history` |
| `partials/` | Explorer building blocks (controls, facets, search, stream, trace groups, pane, pagination) |
| `widgets/` | Activity timeline widget and the overview sections |
| `components/` | Shared UI components (card, chip, stat, diff table, code block, sparkline, …) |

Delete every published file you did not change. Published views are not updated with the plugin, and the page classes they call are internal API that may change in minor releases. Always escape entry data with `{{ }}`.

## Translations

```bash
php artisan vendor:publish --tag=eagle-eye-filament-translations
```

The `eagle-eye-filament::detail` file contains the section titles and field labels of the event detail sections (`sections.*`, `labels.*`) and the fallback page name used in Livewire request titles (`title.this_page`). Add `lang/vendor/eagle-eye-filament/{locale}/detail.php` for other languages. Missing keys fall back to English.

The rest of the interface is English in 1.0. Request and event titles come from the core translation files (`eagle-eye::titles`).

## Styling

The stylesheet `resources/dist/eagle-eye-filament.css` is plain CSS registered as a Filament asset (`eagle-eye-filament`). It needs no custom theme and no Tailwind build.

- All rules are scoped under `.ee` and use `ee-` class names, so they do not leak into the rest of the panel.
- Colours derive from Filament's CSS variables (`--gray-*`, `--primary-600`), so the plugin follows your panel's primary and gray palettes. Dark mode follows Filament's `.dark` class.
- To adjust the look, override the `--ee-*` custom properties in your own panel stylesheet or theme. Use a slightly more specific selector so the order in which stylesheets load does not matter:

```css
.fi-body .ee {
    --ee-radius-lg: 8px;
    --ee-gap-page: 16px;
}
```

- Pages use the full content width and adapt with container queries, so they also work inside narrow panels or with a collapsed sidebar.
