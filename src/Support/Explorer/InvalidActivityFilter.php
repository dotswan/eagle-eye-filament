<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Support\Explorer;

use InvalidArgumentException;

final class InvalidActivityFilter extends InvalidArgumentException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
