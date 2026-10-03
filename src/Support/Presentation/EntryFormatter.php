<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Support\Presentation;

use DateTimeImmutable;
use DateTimeZone;
use Dotswan\EagleEye\Data\Actor;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Enums\ActivitySeverity;
use Illuminate\Support\Str;
use JsonException;
use Stringable;
use Throwable;

final class EntryFormatter
{
    public const int MAX_VALUE_LENGTH = 2000;

    private const int JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

    public function value(mixed $value): string
    {
        $rendered = $this->fullValue($value);

        if (mb_strlen($rendered) <= self::MAX_VALUE_LENGTH) {
            return $rendered;
        }

        return mb_substr($rendered, 0, self::MAX_VALUE_LENGTH).'…';
    }

    public function fullValue(mixed $value): string
    {
        return $this->render($value);
    }

    public function isTruncated(mixed $value): bool
    {
        return mb_strlen($this->fullValue($value)) > self::MAX_VALUE_LENGTH;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function displayPayload(array $payload): array
    {
        unset($payload['title_source']);

        return $payload;
    }

    public function shortKey(?string $key): string
    {
        $key = null === $key ? '' : mb_trim($key);

        if ('' === $key) {
            return '';
        }

        if (Str::isUuid($key)) {
            return '…'.mb_substr($key, -8);
        }

        if (Str::isUlid($key)) {
            return '…'.mb_substr($key, -6);
        }

        return $key;
    }

    public function initials(?string $label): string
    {
        $label = null === $label ? '' : mb_trim($label);

        if ('' === $label) {
            return '—';
        }

        $initials = '';

        foreach (array_slice(preg_split('/\s+/', $label) ?: [], 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return '' === $initials ? '—' : $initials;
    }

    public function relativeTime(DateTimeImmutable $at, ?DateTimeImmutable $now = null): string
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $delta = $now->getTimestamp() - $at->getTimestamp();
        $future = $delta < 0;
        $seconds = abs($delta);

        $label = match (true) {
            $seconds < 5       => 'just now',
            $seconds < 60      => $seconds.'s',
            $seconds < 3600    => intdiv($seconds, 60).'m',
            $seconds < 86400   => intdiv($seconds, 3600).'h',
            $seconds < 2592000 => intdiv($seconds, 86400).'d',
            default            => intdiv($seconds, 2592000).'mo',
        };

        if ('just now' === $label) {
            return $label;
        }

        return $future ? 'in '.$label : $label.' ago';
    }

    public function durationLabel(?int $milliseconds): string
    {
        if (null === $milliseconds || $milliseconds < 0) {
            return '—';
        }

        if ($milliseconds < 1000) {
            return $milliseconds.'ms';
        }

        if ($milliseconds < 60000) {
            return $this->trimNumber($milliseconds / 1000).'s';
        }

        $seconds = intdiv($milliseconds % 60000, 1000);

        return intdiv($milliseconds, 60000).'m '.$seconds.'s';
    }

    public function offsetLabel(int $microseconds): string
    {
        $microseconds = max(0, $microseconds);
        $milliseconds = intdiv($microseconds, 1000);

        if ($milliseconds < 1000) {
            return '+'.$milliseconds.'ms';
        }

        return '+'.$this->trimNumber($milliseconds / 1000).'s';
    }

    public function channelTone(ActivityChannel $channel): string
    {
        return match ($channel) {
            ActivityChannel::Http         => 'blue',
            ActivityChannel::Model        => 'violet',
            ActivityChannel::Queue        => 'amber',
            ActivityChannel::Auth         => 'green',
            ActivityChannel::Console      => 'gray',
            ActivityChannel::Mail         => 'cyan',
            ActivityChannel::Notification => 'teal',
            ActivityChannel::OutboundHttp => 'sky',
            ActivityChannel::Exception    => 'red',
            ActivityChannel::Log          => 'gray',
            ActivityChannel::Schedule     => 'indigo',
            ActivityChannel::Query        => 'slate',
            ActivityChannel::Custom       => 'neutral',
        };
    }

    public function severityTone(ActivitySeverity $severity): string
    {
        return match ($severity) {
            ActivitySeverity::Debug     => 'gray',
            ActivitySeverity::Info      => 'blue',
            ActivitySeverity::Notice    => 'blue',
            ActivitySeverity::Warning   => 'amber',
            ActivitySeverity::Error     => 'red',
            ActivitySeverity::Critical  => 'rose',
            ActivitySeverity::Alert     => 'rose',
            ActivitySeverity::Emergency => 'rose',
        };
    }

    public function isError(ActivitySeverity $severity): bool
    {
        return in_array($severity, [
            ActivitySeverity::Error,
            ActivitySeverity::Critical,
            ActivitySeverity::Alert,
            ActivitySeverity::Emergency,
        ], true);
    }

    public function actorLabel(?Actor $actor): string
    {
        if (! $actor instanceof Actor) {
            return 'system';
        }

        if (null !== $actor->label && '' !== $actor->label) {
            return $actor->label;
        }

        if (null !== $actor->id && '' !== $actor->id) {
            return $actor->type.' #'.$actor->id;
        }

        return $actor->type;
    }

    private function render(mixed $value): string
    {
        if (null === $value) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value) || is_string($value)) {
            return var_export($value, true);
        }

        if (is_array($value)) {
            return $this->json($value);
        }

        if ($value instanceof Stringable) {
            try {
                return var_export((string) $value, true);
            } catch (Throwable) {
                return '[object]';
            }
        }

        return $this->json($value);
    }

    private function json(mixed $value): string
    {
        try {
            return json_encode($value, self::JSON_FLAGS | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '[object]';
        }
    }

    private function trimNumber(float $value): string
    {
        return mb_rtrim(mb_rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
