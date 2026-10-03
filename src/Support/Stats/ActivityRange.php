<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Support\Stats;

use DateTimeImmutable;
use DateTimeZone;

enum ActivityRange: string
{
    case Hour = '1h';
    case Day = '24h';
    case Week = '7d';
    case Month = '30d';

    public static function fromLabel(string $label): ?self
    {
        return self::tryFrom($label);
    }

    public function label(): string
    {
        return $this->value;
    }

    public function durationSeconds(): int
    {
        return match ($this) {
            self::Hour  => 3600,
            self::Day   => 86400,
            self::Week  => 604800,
            self::Month => 2592000,
        };
    }

    public function stepSeconds(): int
    {
        return match ($this) {
            self::Hour  => 300,
            self::Day   => 3600,
            self::Week  => 21600,
            self::Month => 86400,
        };
    }

    public function bucketCount(): int
    {
        return intdiv($this->durationSeconds(), $this->stepSeconds());
    }

    public function axisFormat(): string
    {
        return match ($this) {
            self::Hour, self::Day   => 'H:i',
            self::Week, self::Month => 'D d',
        };
    }

    /** @return array{0: DateTimeImmutable, 1: DateTimeImmutable} */
    public function window(?DateTimeImmutable $now = null): array
    {
        $to = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return [$to->modify('-'.$this->durationSeconds().' seconds'), $to];
    }

    /** @return array{0: DateTimeImmutable, 1: DateTimeImmutable} */
    public function previous(?DateTimeImmutable $now = null): array
    {
        [$from] = $this->window($now);

        return [$from->modify('-'.$this->durationSeconds().' seconds'), $from];
    }
}
