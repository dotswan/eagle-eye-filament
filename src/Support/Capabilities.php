<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Support;

use Dotswan\EagleEye\Contracts\Driver\ActivityLogDriverFactoryInterface;
use Dotswan\EagleEye\Contracts\Driver\ActivityLogDriverInterface;
use Dotswan\EagleEye\Enums\Capability;

final class Capabilities
{
    public static function driver(): ActivityLogDriverInterface
    {
        return app(ActivityLogDriverFactoryInterface::class)->driver();
    }

    public static function supports(Capability $capability): bool
    {
        return self::driver()->supports($capability);
    }
}
