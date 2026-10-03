<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Filament\Concerns;

use Filament\Support\Enums\Width;

trait HasFullContentWidth
{
    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }
}
