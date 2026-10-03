# Contributing

Contributions are welcome through pull requests on [GitHub](https://github.com/dotswan/eagle-eye-filament).

## Setup

```bash
git clone https://github.com/dotswan/eagle-eye-filament.git
cd eagle-eye-filament
composer install
composer check
```

`composer check` runs Rector (dry run), Pint (`--test`), PHPStan (level 8) and the Pest suite. A pull request must pass it. Individual steps:

| Command | Purpose |
|---|---|
| `composer test` | Pest suite (Orchestra Testbench, in-memory SQLite) |
| `composer analyse` | PHPStan / Larastan |
| `composer lint` / `composer format` | Check / fix code style with Pint |
| `composer rector:dry` / `composer rector` | Check / apply Rector rules |

## Developing against an unreleased core

The plugin depends on [`dotswan/eagle-eye`](https://github.com/dotswan/eagle-eye). To test changes that need an unreleased core, point Composer at a local checkout with a path repository and give it a version that satisfies `^1.0`. Do not commit these changes.

```bash
composer config repositories.eagle-eye '{"type": "path", "url": "../eagle-eye", "options": {"symlink": true, "versions": {"dotswan/eagle-eye": "1.99.0"}}}'
composer update dotswan/eagle-eye
```

The same approach works for trying a local copy of this plugin inside an application: add a path repository for `eagle-eye-filament` in the application's `composer.json`.

## Guidelines

- Add or update tests for every behaviour change. Tests live in `tests/Feature` and `tests/Unit`.
- Read activity data only through the core repository contracts (`ActivityRepositoryInterface` and friends). Do not add Eloquent models for activity data.
- Authorize every page, widget, Livewire action and data method through `LogAccessPolicyInterface`.
- Keep every repository read bounded and check driver capabilities before using optional features.
- Escape all output (`{{ }}`); never render entry data with `{!! !!}`.
- Add an entry under `## Unreleased` in `CHANGELOG.md` for user-facing changes.

## Stylesheet

`resources/dist/eagle-eye-filament.css` is hand-written plain CSS. There is no Tailwind or Node build step: edit the file directly and commit it. Keep these rules:

- Scope every rule under `.ee` and prefix classes with `ee-`.
- Use the existing `--ee-*` tokens; dark mode overrides live under `.dark .ee`.
- Use container queries (`@container ee-page`) rather than viewport media queries for layout.

`tests/Unit/Support/CssContractTest.php` fails when a class used in the views or `src` has no rule, when selectors are duplicated, or when font tokens regress.

Hosts receive the stylesheet through `php artisan filament:assets`, so remind users in the changelog when a release changes it.
