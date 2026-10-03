<?php

declare(strict_types=1);

use Dotswan\EagleEye\Data\Actor;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Enums\ActivitySeverity;
use Dotswan\EagleEyeFilament\Support\Presentation\EntryFormatter;

it('renders scalars and arrays with the expected value semantics', function (): void {
    $formatter = new EntryFormatter();

    expect($formatter->value(null))->toBe('NULL')
        ->and($formatter->value(true))->toBe('true')
        ->and($formatter->value(false))->toBe('false')
        ->and($formatter->value(12))->toBe('12')
        ->and($formatter->value('hello'))->toBe("'hello'")
        ->and($formatter->value(['a' => 1]))->toBe('{"a":1}');
});

it('truncates oversized values and reports truncation', function (): void {
    $formatter = new EntryFormatter();
    $long = str_repeat('x', 2500);

    expect(mb_strlen($formatter->value($long)))->toBe(EntryFormatter::MAX_VALUE_LENGTH + 1)
        ->and($formatter->value($long))->toEndWith('…')
        ->and($formatter->isTruncated($long))->toBeTrue()
        ->and($formatter->isTruncated('short'))->toBeFalse();
});

it('substitutes invalid utf-8 without throwing', function (): void {
    $formatter = new EntryFormatter();

    expect($formatter->value(['bad' => "\xB1\x31"]))->toContain('bad');
});

it('formats relative times durations offsets and initials', function (): void {
    $formatter = new EntryFormatter();
    $now = new DateTimeImmutable('2026-09-16 12:00:00', new DateTimeZone('UTC'));

    expect($formatter->relativeTime($now->modify('-30 seconds'), $now))->toBe('30s ago')
        ->and($formatter->relativeTime($now->modify('-3 hours'), $now))->toBe('3h ago')
        ->and($formatter->relativeTime($now->modify('+2 minutes'), $now))->toBe('in 2m')
        ->and($formatter->relativeTime($now->modify('+3 seconds'), $now))->toBe('just now')
        ->and($formatter->durationLabel(null))->toBe('—')
        ->and($formatter->durationLabel(445))->toBe('445ms')
        ->and($formatter->durationLabel(1250))->toBe('1.25s')
        ->and($formatter->durationLabel(61000))->toBe('1m 1s')
        ->and($formatter->offsetLabel(445000))->toBe('+445ms')
        ->and($formatter->offsetLabel(0))->toBe('+0ms')
        ->and($formatter->offsetLabel(2500000))->toBe('+2.5s')
        ->and($formatter->initials('Sara Bakker'))->toBe('SB')
        ->and($formatter->initials('adminUser'))->toBe('A')
        ->and($formatter->initials(null))->toBe('—');
});

it('maps channel and severity tones and actor labels', function (): void {
    $formatter = new EntryFormatter();

    expect($formatter->channelTone(ActivityChannel::Http))->toBe('blue')
        ->and($formatter->channelTone(ActivityChannel::Model))->toBe('violet')
        ->and($formatter->channelTone(ActivityChannel::Auth))->toBe('green')
        ->and($formatter->channelTone(ActivityChannel::Custom))->toBe('neutral')
        ->and($formatter->severityTone(ActivitySeverity::Notice))->toBe('blue')
        ->and($formatter->severityTone(ActivitySeverity::Emergency))->toBe('rose')
        ->and($formatter->severityTone(ActivitySeverity::Warning))->toBe('amber')
        ->and($formatter->isError(ActivitySeverity::Error))->toBeTrue()
        ->and($formatter->isError(ActivitySeverity::Warning))->toBeFalse()
        ->and($formatter->actorLabel(null))->toBe('system')
        ->and($formatter->actorLabel(new Actor('user', '7', 'Sara')))->toBe('Sara')
        ->and($formatter->actorLabel(new Actor('user', '7')))->toBe('user #7')
        ->and($formatter->actorLabel(new Actor('system')))->toBe('system');
});

it('renders stringable and json serializable objects as strings or json', function (): void {
    $formatter = new EntryFormatter();
    $stringable = new class () implements Stringable {
        public function __toString(): string
        {
            return 'value-object';
        }
    };
    $json = new class () implements JsonSerializable {
        /** @return array<string, int> */
        public function jsonSerialize(): array
        {
            return ['id' => 9];
        }
    };

    expect($formatter->value($stringable))->toBe("'value-object'")
        ->and($formatter->value($json))->toBe('{"id":9}');
});

it('shortens uuids and ulids while keeping other keys intact', function (): void {
    $formatter = new EntryFormatter();

    expect($formatter->shortKey('f111c688-1234-5678-9abc-def012345678'))->toBe('…12345678')
        ->and($formatter->shortKey('01j'.str_repeat('0', 21).'r1'))->toBe('…0000r1')
        ->and($formatter->shortKey('01J'.str_repeat('0', 21).'R1'))->toBe('…0000R1')
        ->and($formatter->shortKey('plain-key'))->toBe('plain-key')
        ->and($formatter->shortKey(null))->toBe('');
});

it('gives uuidv7 keys created a second apart different short keys', function (): void {
    $formatter = new EntryFormatter();

    $first = $formatter->shortKey('01925c2e-1111-7000-8000-000000000001');
    $second = $formatter->shortKey('01925c2e-1111-7000-8000-000000000002');

    expect($first)->not->toBe($second);
});
