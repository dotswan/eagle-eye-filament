# Authorization

The plugin has no permission system of its own. Every decision goes through the core contract `Dotswan\EagleEye\Contracts\Security\LogAccessPolicyInterface`:

```php
interface LogAccessPolicyInterface
{
    public function canView(?object $viewer = null): bool;

    public function canReveal(ActivityEntry $entry, string $path, ?object $viewer = null): bool;
}
```

The viewer passed in is `auth()->user()`. Inside a Filament panel this is the user of the panel's auth guard.

## Default: Gate abilities

The core binds `Dotswan\EagleEye\Security\GateAccessPolicy`, which checks two Gate abilities:

| Ability | Arguments | Controls |
|---|---|---|
| `viewActivityLog` | `$user` | Every page, widget and sidebar item of the plugin |
| `revealActivityLog` | `$user`, `ActivityEntry $entry`, `string $path` | Revealing one sealed (encrypted) field on the event detail page |

Laravel denies undefined abilities, so **nothing is visible until you define them**:

```php
use Dotswan\EagleEye\Data\ActivityEntry;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewActivityLog', fn ($user): bool => $user->hasRole('admin'));

    Gate::define('revealActivityLog', fn ($user, ActivityEntry $entry, string $path): bool =>
        $user->hasRole('security-officer'));
}
```

A `Gate::before()` callback that returns `true` (common for super-admins) also grants both abilities.

## Custom policy

Bind your own implementation in the `register()` method of an application service provider. Application providers register after package providers, so your binding replaces the core default:

```php
use App\Security\ActivityLogPolicy;
use Dotswan\EagleEye\Contracts\Security\LogAccessPolicyInterface;

public function register(): void
{
    $this->app->singleton(LogAccessPolicyInterface::class, ActivityLogPolicy::class);
}
```

```php
namespace App\Security;

use Dotswan\EagleEye\Contracts\Security\LogAccessPolicyInterface;
use Dotswan\EagleEye\Data\ActivityEntry;

final class ActivityLogPolicy implements LogAccessPolicyInterface
{
    public function canView(?object $viewer = null): bool
    {
        return $viewer?->can('view activity log') ?? false;
    }

    public function canReveal(ActivityEntry $entry, string $path, ?object $viewer = null): bool
    {
        return $viewer?->can('reveal activity secrets') ?? false;
    }
}
```

The same policy also protects the core's optional JSON API, so one binding covers both.

Keep `canView()` cheap: it runs several times per request (navigation, page, widgets, every Livewire call). Cache expensive lookups on the user object.

## What is enforced

- Sidebar items, pages and widgets are hidden when `canView()` returns `false`; direct URLs return 403.
- `canView()` is checked again on every Livewire request and in every data method, so a revoked permission takes effect on the next click, not only on the next page load.
- Record, trace, actor, subject and cursor context is `#[Locked]`; a tampered Livewire payload is rejected.
- Filters, dates, IDs and search input are validated before any repository call. Values that can only come from tampering (unknown enum values, oversized IDs or arrays) return 422.
- Sealed values are never rendered in the explorer pane. On the detail page they are revealed one field at a time; the core repository checks `canReveal()` and writes an audit entry before returning the plaintext. The plaintext is kept only for the current request.
- All output is escaped.

## Panel-level access

The plugin does not restrict which panels it can be registered on. If you have several panels, register it only on the panels whose users may be granted access, and keep the policy as the single source of truth.
