<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Support\Explorer;

use DateTimeImmutable;
use DateTimeZone;
use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Enums\ActivitySeverity;

final readonly class ActivityFilterState
{
    public const int MAX_CHANNELS = 13;
    public const int MAX_SEVERITIES = 8;
    public const int MAX_ACTIONS = 20;
    public const int MAX_SEARCH = 256;
    public const int MAX_ID = 256;
    public const int MAX_RANGE_DAYS = 366;

    /**
     * @param list<ActivityChannel>  $channels
     * @param list<ActivitySeverity> $severities
     * @param list<string>           $actions
     */
    public function __construct(
        public array $channels = [],
        public array $severities = [],
        public array $actions = [],
        public ?string $actorType = null,
        public ?string $actorId = null,
        public ?string $subjectType = null,
        public ?string $subjectId = null,
        public ?string $traceId = null,
        public ?string $search = null,
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $until = null,
    ) {
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $timezone = new DateTimeZone((string) config('app.timezone', 'UTC'));
        [$from, $until] = self::resolveRange($input['from'] ?? null, $input['until'] ?? null, $timezone);

        return new self(
            channels: self::channels($input['channels'] ?? []),
            severities: self::severities($input['severities'] ?? []),
            actions: self::actions($input['actions'] ?? []),
            actorType: self::identifier($input['actorType'] ?? null, 'actorType'),
            actorId: self::identifier($input['actorId'] ?? null, 'actorId'),
            subjectType: self::identifier($input['subjectType'] ?? null, 'subjectType'),
            subjectId: self::identifier($input['subjectId'] ?? null, 'subjectId'),
            traceId: self::trace($input['trace'] ?? $input['traceId'] ?? null),
            search: self::search($input['search'] ?? $input['q'] ?? null),
            from: $from,
            until: $until,
        );
    }

    public function applyTo(ActivityQuery $query): ActivityQuery
    {
        $query->channels(...$this->channels);
        $query->severities(...$this->severities);

        if ([] !== $this->actions) {
            $query->actions(...$this->actions);
        }

        if (null !== $this->actorType && null !== $this->actorId) {
            $query->forActor($this->actorType, $this->actorId);
        } elseif (null !== $this->actorType) {
            $query->actorType = $this->actorType;
        }

        if (null !== $this->subjectType && null !== $this->subjectId) {
            $query->forSubject($this->subjectType, $this->subjectId);
        } elseif (null !== $this->subjectType) {
            $query->subjectType = $this->subjectType;
        }

        if (null !== $this->traceId) {
            $query->forTrace($this->traceId);
        }

        if (null !== $this->search) {
            $query->search($this->search);
        }

        if ($this->from instanceof DateTimeImmutable && $this->until instanceof DateTimeImmutable) {
            $query->between($this->from, $this->until);
        }

        return $query;
    }

    public function signature(): string
    {
        return hash('sha256', serialize([
            array_map(static fn (ActivityChannel $channel): string => $channel->value, $this->channels),
            array_map(static fn (ActivitySeverity $severity): string => $severity->value, $this->severities),
            $this->actions,
            $this->actorType,
            $this->actorId,
            $this->subjectType,
            $this->subjectId,
            $this->traceId,
            $this->search,
            $this->from?->format('Y-m-d\TH:i:s'),
            $this->until?->format('Y-m-d\TH:i:s'),
        ]));
    }

    /** @return list<ActivityChannel> */
    private static function channels(mixed $value): array
    {
        if (null === $value || '' === $value) {
            return [];
        }

        if (! is_array($value)) {
            throw new InvalidActivityFilter('channels', 'Activity channel filters must be an array.');
        }

        if (count($value) > self::MAX_CHANNELS) {
            throw new InvalidActivityFilter('channels', 'Too many activity channel filters.');
        }

        $resolved = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw new InvalidActivityFilter('channels', 'Activity channel filters must be strings.');
            }

            $channel = ActivityChannel::tryFrom($item);

            if (! $channel instanceof ActivityChannel) {
                throw new InvalidActivityFilter('channels', "Unknown activity channel [{$item}].");
            }

            $resolved[$channel->value] = $channel;
        }

        return array_values($resolved);
    }

    /** @return list<ActivitySeverity> */
    private static function severities(mixed $value): array
    {
        if (null === $value || '' === $value) {
            return [];
        }

        if (! is_array($value)) {
            throw new InvalidActivityFilter('severities', 'Activity severity filters must be an array.');
        }

        if (count($value) > self::MAX_SEVERITIES) {
            throw new InvalidActivityFilter('severities', 'Too many activity severity filters.');
        }

        $resolved = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw new InvalidActivityFilter('severities', 'Activity severity filters must be strings.');
            }

            $severity = ActivitySeverity::tryFrom($item);

            if (! $severity instanceof ActivitySeverity) {
                throw new InvalidActivityFilter('severities', "Unknown activity severity [{$item}].");
            }

            $resolved[$severity->value] = $severity;
        }

        return array_values($resolved);
    }

    /** @return list<string> */
    private static function actions(mixed $value): array
    {
        if (null === $value || '' === $value) {
            return [];
        }

        if (! is_array($value)) {
            throw new InvalidActivityFilter('actions', 'Activity action filters must be an array.');
        }

        if (count($value) > self::MAX_ACTIONS) {
            throw new InvalidActivityFilter('actions', 'Too many activity action filters.');
        }

        $resolved = [];

        foreach ($value as $item) {
            if (! is_string($item) || 1 !== preg_match('/^[A-Za-z0-9_.:\-]{1,191}$/', $item)) {
                throw new InvalidActivityFilter('actions', 'An activity action filter is invalid.');
            }

            $resolved[$item] = $item;
        }

        return array_values($resolved);
    }

    private static function identifier(mixed $value, string $field): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (! is_string($value) || mb_strlen($value, '8bit') > self::MAX_ID || 1 === preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new InvalidActivityFilter($field, 'The activity identifier filter is invalid.');
        }

        return $value;
    }

    private static function trace(mixed $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (! is_string($value) || 1 !== preg_match('/^[a-f0-9]{32}$/', $value) || str_repeat('0', 32) === $value) {
            throw new InvalidActivityFilter('trace', 'The activity trace filter is invalid.');
        }

        return $value;
    }

    private static function search(mixed $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidActivityFilter('search', 'The activity search term is invalid.');
        }

        $search = mb_trim($value);

        if (mb_strlen($search) > self::MAX_SEARCH) {
            throw new InvalidActivityFilter('search', 'The activity search term is too long.');
        }

        return '' === $search ? null : $search;
    }

    /** @return array{0: ?DateTimeImmutable, 1: ?DateTimeImmutable} */
    private static function resolveRange(mixed $fromValue, mixed $untilValue, DateTimeZone $timezone): array
    {
        $fromPresent = null !== $fromValue && '' !== $fromValue;
        $untilPresent = null !== $untilValue && '' !== $untilValue;

        if (! $fromPresent && ! $untilPresent) {
            return [null, null];
        }

        $from = $fromPresent ? self::strictDay($fromValue, $timezone, 'from') : null;
        $until = $untilPresent ? self::strictDay($untilValue, $timezone, 'until') : null;
        $now = new DateTimeImmutable('now', $timezone);

        if (! $from instanceof DateTimeImmutable) {
            if (! $until instanceof DateTimeImmutable) {
                throw new InvalidActivityFilter('range', 'The activity date range is invalid.');
            }

            $end = $until->modify('+1 day');

            return [$end->modify('-'.self::MAX_RANGE_DAYS.' days'), $end];
        }

        if (! $until instanceof DateTimeImmutable) {
            if ($from > $now || $now->getTimestamp() - $from->getTimestamp() > self::MAX_RANGE_DAYS * 86400) {
                throw new InvalidActivityFilter('range', 'The activity date range may not exceed 366 days.');
            }

            return [$from, $now];
        }

        if ($from > $until) {
            throw new InvalidActivityFilter('range', 'The activity end date must follow the start date.');
        }

        $end = $until->modify('+1 day');

        if ($end->getTimestamp() - $from->getTimestamp() > self::MAX_RANGE_DAYS * 86400) {
            throw new InvalidActivityFilter('range', 'The activity date range may not exceed 366 days.');
        }

        return [$from, $end];
    }

    private static function strictDay(mixed $value, DateTimeZone $timezone, string $field): DateTimeImmutable
    {
        if (! is_string($value) || 10 !== mb_strlen($value, '8bit')) {
            throw new InvalidActivityFilter($field, 'The activity date must use the YYYY-MM-DD format.');
        }

        if (1 !== preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $date)) {
            throw new InvalidActivityFilter($field, 'The activity date must use the YYYY-MM-DD format.');
        }

        if (! checkdate((int) $date[2], (int) $date[3], (int) $date[1])) {
            throw new InvalidActivityFilter($field, 'The activity date is not a valid calendar date.');
        }

        return new DateTimeImmutable($value, $timezone);
    }
}
