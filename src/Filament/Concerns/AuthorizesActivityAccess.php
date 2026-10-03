<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Filament\Concerns;

use Dotswan\EagleEye\Contracts\Security\LogAccessPolicyInterface;

trait AuthorizesActivityAccess
{
    protected function authorizeAccess(): void
    {
        abort_unless(app(LogAccessPolicyInterface::class)->canView(auth()->user()), 403);
    }
}
