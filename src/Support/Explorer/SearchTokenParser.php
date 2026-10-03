<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Support\Explorer;

use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Enums\ActivitySeverity;
use Illuminate\Support\Str;

final class SearchTokenParser
{
    public const int MAX_CHARACTERS = 256;
    public const int MAX_TOKENS = 12;
    public const int MAX_ID = 256;

    /** @var list<string> */
    private const array KEYS = ['channel', 'severity', 'action', 'actor', 'subject', 'trace'];

    public function parse(string $input): ParsedSearch
    {
        /** @var list<string> $errors */
        $errors = [];
        /** @var list<string> $warnings */
        $warnings = [];
        $input = mb_trim($input);

        if (mb_strlen($input) > self::MAX_CHARACTERS) {
            $errors[] = 'Search input is limited to 256 characters.';
        }

        $tokens = $this->tokenize($input);

        if (count($tokens) > self::MAX_TOKENS) {
            $errors[] = 'Search accepts at most 12 tokens.';
            $tokens = array_slice($tokens, 0, self::MAX_TOKENS);
        }

        /** @var array<string, mixed> $criteria */
        $criteria = [
            'channels'    => [],
            'severities'  => [],
            'actions'     => [],
            'actorType'   => null,
            'actorId'     => null,
            'subjectType' => null,
            'subjectId'   => null,
            'trace'       => null,
        ];
        $freeText = [];

        foreach ($tokens as $token) {
            if ($this->isQuoted($token)) {
                $freeText[] = mb_substr($token, 1, -1);

                continue;
            }

            $colon = mb_strpos($token, ':');

            if (false === $colon) {
                $freeText[] = $token;

                continue;
            }

            $key = mb_strtolower(mb_substr($token, 0, $colon));

            if (! in_array($key, self::KEYS, true)) {
                $freeText[] = $token;

                continue;
            }

            $value = mb_substr($token, $colon + 1);

            switch ($key) {
                case 'channel':
                    $this->channel($value, $criteria, $errors);
                    break;
                case 'severity':
                    $this->severity($value, $criteria, $errors);
                    break;
                case 'action':
                    $this->action($value, $criteria, $errors);
                    break;
                case 'actor':
                    $this->actor($value, $criteria, $errors, $warnings);
                    break;
                case 'subject':
                    $this->subject($value, $criteria, $errors, $warnings);
                    break;
                case 'trace':
                    $this->trace($value, $criteria, $errors, $warnings);
                    break;
            }
        }

        /** @var array{channels: list<string>, severities: list<string>, actions: list<string>, actorType: ?string, actorId: ?string, subjectType: ?string, subjectId: ?string, trace: ?string} $criteria */
        return new ParsedSearch(
            $criteria,
            implode(' ', array_values(array_filter($freeText, static fn (string $part): bool => '' !== $part))),
            $errors,
            $warnings,
        );
    }

    /**
     * @param array<string, mixed> $criteria
     * @param list<string>         $errors
     */
    private function channel(string $value, array &$criteria, array &$errors): void
    {
        $channel = ActivityChannel::tryFrom($value) ?? ActivityChannel::tryFrom(mb_strtolower($value));

        if (! $channel instanceof ActivityChannel) {
            $errors[] = "Unknown activity channel '{$value}'.";

            return;
        }

        $channels = $this->stringList($criteria, 'channels');

        if (! in_array($channel->value, $channels, true)) {
            $channels[] = $channel->value;
        }

        $criteria['channels'] = $channels;
    }

    /**
     * @param array<string, mixed> $criteria
     * @param list<string>         $errors
     */
    private function severity(string $value, array &$criteria, array &$errors): void
    {
        $severities = $this->stringList($criteria, 'severities');

        if (1 === preg_match('/^>=\s*([a-z]+)$/i', $value, $match)) {
            $minimum = ActivitySeverity::tryFrom(mb_strtolower($match[1]));

            if (! $minimum instanceof ActivitySeverity) {
                $errors[] = "Unknown activity severity '{$value}'.";

                return;
            }

            $start = array_search($minimum, ActivitySeverity::cases(), true);

            if (false === $start) {
                return;
            }

            foreach (array_slice(ActivitySeverity::cases(), $start) as $severity) {
                if (! in_array($severity->value, $severities, true)) {
                    $severities[] = $severity->value;
                }
            }

            $criteria['severities'] = $severities;

            return;
        }

        $severity = ActivitySeverity::tryFrom($value) ?? ActivitySeverity::tryFrom(mb_strtolower($value));

        if (! $severity instanceof ActivitySeverity) {
            $errors[] = "Unknown activity severity '{$value}'.";

            return;
        }

        if (! in_array($severity->value, $severities, true)) {
            $severities[] = $severity->value;
        }

        $criteria['severities'] = $severities;
    }

    /**
     * @param array<string, mixed> $criteria
     * @param list<string>         $errors
     */
    private function action(string $value, array &$criteria, array &$errors): void
    {
        if (1 !== preg_match('/^[A-Za-z0-9_.:\-]{1,191}$/', $value)) {
            $errors[] = "Unknown activity action '{$value}'.";

            return;
        }

        $actions = $this->stringList($criteria, 'actions');

        if (! in_array($value, $actions, true)) {
            $actions[] = $value;
        }

        $criteria['actions'] = $actions;
    }

    /**
     * @param array<string, mixed> $criteria
     * @param list<string>         $errors
     * @param list<string>         $warnings
     */
    private function actor(string $value, array &$criteria, array &$errors, array &$warnings): void
    {
        $separator = $this->separatorPosition($value);
        $type = null === $separator ? $value : mb_substr($value, 0, $separator);
        $id = null === $separator ? null : mb_substr($value, $separator + 1);

        if ('' === $type || ! $this->validIdentifier($type) || (null !== $id && '' !== $id && ! $this->validIdentifier($id))) {
            $errors[] = "Unknown actor filter '{$value}'.";

            return;
        }

        if (null !== $this->nullableString($criteria, 'actorType') || null !== $this->nullableString($criteria, 'actorId')) {
            $warnings[] = 'Multiple actor filters; using the last one.';
        }

        $criteria['actorType'] = $type;
        $criteria['actorId'] = null === $id || '' === $id ? null : $id;
    }

    /**
     * @param array<string, mixed> $criteria
     * @param list<string>         $errors
     * @param list<string>         $warnings
     */
    private function subject(string $value, array &$criteria, array &$errors, array &$warnings): void
    {
        $separator = $this->separatorPosition($value);
        $type = null === $separator ? $value : mb_substr($value, 0, $separator);
        $id = null === $separator ? null : mb_substr($value, $separator + 1);

        if ('' === $type) {
            $errors[] = "Unknown subject filter '{$value}'.";

            return;
        }

        if (str_contains($type, '\\')) {
            $type = class_basename($type);
        }

        $type = Str::snake($type);

        if (! $this->validIdentifier($type) || (null !== $id && '' !== $id && ! $this->validIdentifier($id))) {
            $errors[] = "Unknown subject filter '{$value}'.";

            return;
        }

        if (null !== $this->nullableString($criteria, 'subjectType') || null !== $this->nullableString($criteria, 'subjectId')) {
            $warnings[] = 'Multiple subject filters; using the last one.';
        }

        $criteria['subjectType'] = $type;
        $criteria['subjectId'] = null === $id || '' === $id ? null : $id;
    }

    /**
     * @param array<string, mixed> $criteria
     * @param list<string>         $errors
     * @param list<string>         $warnings
     */
    private function trace(string $value, array &$criteria, array &$errors, array &$warnings): void
    {
        if (1 !== preg_match('/^[a-f0-9]{32}$/', $value) || str_repeat('0', 32) === $value) {
            $errors[] = "Unknown trace filter '{$value}'.";

            return;
        }

        if (null !== $this->nullableString($criteria, 'trace')) {
            $warnings[] = 'Multiple trace filters; using the last one.';
        }

        $criteria['trace'] = $value;
    }

    /** @return list<string> */
    private function tokenize(string $input): array
    {
        $tokens = [];
        $current = '';
        $quoted = false;
        $length = mb_strlen($input);

        for ($index = 0; $index < $length; $index++) {
            $char = mb_substr($input, $index, 1);

            if ('"' === $char) {
                $quoted = ! $quoted;
                $current .= $char;

                continue;
            }

            if (! $quoted && '' === mb_trim($char)) {
                if ('' !== $current) {
                    $tokens[] = $current;
                    $current = '';
                }

                continue;
            }

            $current .= $char;
        }

        if ('' !== $current) {
            $tokens[] = $current;
        }

        return $tokens;
    }

    private function isQuoted(string $token): bool
    {
        return mb_strlen($token) >= 2 && str_starts_with($token, '"') && str_ends_with($token, '"');
    }

    private function separatorPosition(string $value): ?int
    {
        $colon = mb_strpos($value, ':');
        $hash = mb_strpos($value, '#');

        if (false === $colon) {
            return false === $hash ? null : $hash;
        }

        if (false === $hash) {
            return $colon;
        }

        return min($colon, $hash);
    }

    private function validIdentifier(string $value): bool
    {
        return mb_strlen($value, '8bit') <= self::MAX_ID && 0 === preg_match('/[\x00-\x1F\x7F]/', $value);
    }

    /**
     * @param array<string, mixed> $criteria
     * @return list<string>
     */
    private function stringList(array $criteria, string $key): array
    {
        $value = $criteria[$key] ?? [];

        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }

    /** @param array<string, mixed> $criteria */
    private function nullableString(array $criteria, string $key): ?string
    {
        $value = $criteria[$key] ?? null;

        return is_string($value) && '' !== $value ? $value : null;
    }
}
