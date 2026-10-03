<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Tests\Support;

use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class ThrowingLivewireComponent extends Component
{
    public function submit(): never
    {
        throw ValidationException::withMessages(['email' => 'Invalid.']);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
