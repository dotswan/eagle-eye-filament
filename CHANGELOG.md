# Changelog

All notable changes to `dotswan/eagle-eye-filament` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

### Added

- Laravel 13 support. The `illuminate/support` constraint now allows `^13.0`, and CI covers Laravel 12 and 13.

### Removed

- Laravel 11 support. The `illuminate/support` constraint, the dev dependencies and the CI matrix now require Laravel 12 or newer.

## 1.0.0 - 2026-10-03

Initial public release.

### Added

- `EagleEyeFilamentPlugin` for Filament 5 panels, with `widgets()`, `withoutWidgets()`, `navigationGroup()` and `navigationSort()`.
- **Activity overview** page: KPI cards with sparklines and deltas, events-by-channel chart, severity mix, failing traces, latest requests and busiest actors, with a 1h / 24h / 7d / 30d range switch. Each section loads lazily.
- **Activity explorer**: facet rail (channel, severity, action) with counts, structured token search (`channel:`, `severity:`, `action:`, `actor:`, `subject:`, `trace:`), *Stream* and *By trace* views, an inline detail pane, a mandatory time window, cursor pagination with 25 / 50 / 100 rows per page, and shareable URLs.
- **Event detail** page: per-channel detail sections, field-level changes (including changed paths inside JSON columns), payload viewer, previous/next navigation inside the trace, related entries and policy-gated reveal of sealed values.
- **Trace timeline**, **Recent traces**, **Actor activity** (stats, 7 × 24 heatmap, top actions) and **Record history** pages.
- Dashboard widgets: `ActivityTimelineWidget` (optionally scoped to one subject), `ActivityVolumeChart`, `TopActorsChart` and `ErrorRateChart`.
- Livewire interaction capture: component, method and property names (never values) are attached to the core `http.request` entry, and Filament page, resource and action labels become readable request titles.
- A single plain-CSS stylesheet with light and dark mode, served through `php artisan filament:assets`. No custom Filament theme or build step is required.
- Publishable config, views and translations.

### Security

- Every page, widget, Livewire request and data method is authorized through the core `LogAccessPolicyInterface`. Revealing a sealed value additionally requires `canReveal()` and is audited by the core.
- Record, trace, actor, subject and cursor context is locked against client-side tampering; user input is validated before any repository call.

### Performance

- All reads go through the core repository contracts, so database, Loki, memory and composite drivers share the same UI. Features that need a driver capability are hidden or shown as unavailable instead of running expensive fallbacks.
- Every read is bounded (page sizes, trace size, facet and ranking limits), facet counts are cached for 60 seconds, and dashboard charts do not poll.
