# Architecture and performance

## Design

- **Read-only.** The plugin never creates, edits or deletes activity entries. The only state-changing action is revealing a sealed value, which the core audits.
- **Driver-agnostic.** Every read goes through the core contracts: `ActivityRepositoryInterface` (search, find, count, aggregate, aggregateSeries, aggregateTop, reveal), `ActivityEntryPresenterInterface` (display labels) and `ActivityIdentityResolverInterface` (batched actor labels). There is no Eloquent activity model, so database, Loki, memory and `multi` drivers behave the same.
- **Capability-aware.** Before using an optional feature, the plugin asks the active driver whether it supports the matching `Capability`. Unsupported features are hidden or shown as unavailable; the plugin never emulates them with large reads.
- **No activity objects in Livewire state.** Public Livewire properties hold only IDs, filters and strings. Entries are loaded per request and memoized in private properties.
- The core never depends on the plugin. The plugin hooks into the core only through its public container tags: `eagle-eye.enrichers` (Livewire payload) and `eagle-eye.title_resolvers` (readable Filament request titles).

## Components

| Class | Kind | Purpose |
|---|---|---|
| `EagleEyeFilamentPlugin` | Filament plugin | Registers the resource, pages and widgets on a panel |
| `EagleEyeFilamentServiceProvider` | Service provider | Config, views, translations, Blade components, Livewire alias, stylesheet, Livewire hooks |
| `Filament\Resources\ActivityLogResource` | Resource | Explorer (`ListActivityLogs`) and event detail (`ViewActivityLog`) |
| `Filament\Livewire\ExplorerPane` | Livewire (`ee-explorer-pane`) | Explorer detail pane, URL key `selected` |
| `Filament\Pages\*` | Pages | Overview, trace timeline, recent traces, actor activity, record history |
| `Filament\Widgets\*` | Widgets | Dashboard widgets and lazy overview sections |
| `Support\*` | Plain classes | Filter validation, token parsing, cursor history, page loading, trace reading, statistics, presentation |
| `Support\Livewire\*` | Scoped services | Livewire interaction capture and `http.request` enrichment |
| `Support\Title\FilamentHttpTitleResolver` | Title resolver | Maps Filament routes to page and resource labels without booting pages |

Classes under `Support\` and the overview sections are internal. The public API is the plugin, the pages and resource (for `getUrl()`), the four dashboard widgets, the config, the views and the translations.

## Capabilities

| Capability | Without it |
|---|---|
| `Aggregation` | No overview page (and no sidebar item), no facet counts, no volume/error charts, no editor count on record history |
| `SeriesAggregation` | Overview sparklines, channel chart, severity mix and actor heatmap show "not supported by the active log backend" |
| `RankedAggregation` | `TopActorsChart` is hidden |
| `FullTextSearch` | Free-text search is disabled; `channel:`, `severity:`, `action:`, `actor:`, `subject:` and `trace:` tokens still work |
| `ExactCount` | Totals are shown as approximations ("≈", "N per page") |
| `UnboundedTimeRange` | "First seen" on record history is marked approximate when it reaches the driver's readable window |

## Bounds

| Read | Bound |
|---|---|
| Explorer page | 25, 50 or 100 entries (`per_page`) |
| Actor, record history, recent traces pages | 20 entries |
| Explorer time window | Always set; default 24 hours, custom ranges up to 366 days |
| Cursor history | 32 pages remembered per filter; unknown jumps return 422 |
| Trace | 50 entries (larger traces show an "incomplete" banner) |
| Facets | channel and severity groups, top 8 actions |
| Rankings | top 5 actors and top 5 actions |
| Overview failures / latest requests | 20 / 5 entries |
| Search input | 256 characters, 12 tokens |
| Filter arrays | 13 channels, 8 severities, 20 actions |
| IDs | 256 bytes, no control characters |
| Rendered values | 2,000 characters per value; JSON diffs capped by path, node and depth limits |

Actor labels are resolved in one batch per page and trace event counts use one aggregate per page, so there are no N+1 reads against the backend.

## Read budget per page

Approximate repository calls on the first render. "Lazy" calls run in follow-up Livewire requests after the page is visible.

| Page | First render | Lazy / on demand |
|---|---|---|
| Overview | none (shell only) | Traffic: 2 series + 2 aggregates · Failures: 1 search · Latest requests: 1 search + 1 aggregate · Busiest actors: 1 ranking + 1 aggregate |
| Explorer | 1 search (+ 1 find for the selected entry) | Facets: up to 3 aggregates (+ 1 count); Context tab: 1 search + 1 count |
| Event detail | 1 find, 1 trace search, 1 related-subject search | – |
| Trace timeline | 1 search | – |
| Recent traces | 1 search + 1 aggregate | – |
| Actor activity | 1 search | 1 series, 1 count, 1 search, 1 ranking |
| Record history | 1 search | 1 count, 1 aggregate, 1 search |

Facet counts and explorer totals are cached for 60 seconds in the default cache store, keyed by viewer and filter signature (`eagle-eye:facets:*`). Only integers and count maps are cached, never entries. Failures are cached for the same period, so a slow backend is not hit repeatedly.

The dashboard charts (`ActivityVolumeChart`, `TopActorsChart`, `ErrorRateChart`) each run one 7-day aggregate on render and do not poll.

## Loki and other remote drivers

- Remote drivers pay network latency per call. The lazy overview and explorer facets keep the first paint independent of that latency.
- Keep explorer windows short. The 24-hour default exists for this reason; 30-day windows on Loki scan far more data.
- For production Loki setups, prefer the core's `multi` driver with the SQL index accelerator: listings, counts, rankings and traces are served from SQL, and bodies from Loki.
- If an aggregate exceeds the backend's limits, the affected section shows "Backend unavailable, try a narrower time range" instead of partial numbers.

See the core's Loki and performance documentation for driver-level tuning.

## Livewire interaction capture

The service provider listens to Livewire's `call` and `update` hooks. Method and property **names** are collected in a request-scoped `LivewireInteractionCollector`; values and arguments are never stored. `LivewireRequestEnricher` (tagged `eagle-eye.enrichers`) attaches the collected data to the core's `http.request` draft and resets the collector. `FilamentHttpTitleResolver` (tagged `eagle-eye.title_resolvers`) turns Filament routes and Livewire actions into titles such as "Viewed Orders", "Opened Order #…1a2b3c4d for editing" or "Delete on Orders". The route map is built once per process from the registered panels without booting any page.

Capture work runs inside the core's capture suppression, so queries it triggers are not recorded. Limits are configured under `livewire.*` (see [Configuration](configuration.md)).

## Presentation

- One stylesheet, scoped under `.ee`, light and dark mode, no external fonts or network requests.
- Charts are plain HTML and CSS with accessible labels; no JavaScript chart library is loaded by the pages.
- The explorer list is keyboard navigable (arrow keys to select, Enter to open) and respects `prefers-reduced-motion`.
- Pages use the full content width and adapt to the available space with container queries: three columns from 1100 px, two below that with filters in a disclosure, one column below 760 px where rows link to the detail page.
