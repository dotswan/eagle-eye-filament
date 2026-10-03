<?php

declare(strict_types=1);

use Dotswan\EagleEyeFilament\Support\Presentation\JsonPathDiff;

it('reports only the changed json paths', function (): void {
    $result = (new JsonPathDiff())->diff('{"a":1,"b":{"c":2}}', '{"a":1,"b":{"c":3}}');

    expect($result['paths'])->toBe([['path' => 'b.c', 'before' => 2, 'after' => 3]])
        ->and($result['truncated'])->toBeFalse()
        ->and($result['formatting_only'])->toBeFalse();
});

it('detects formatting-only json changes', function (): void {
    $result = (new JsonPathDiff())->diff('{"a":1}', '{ "a" : 1 }');

    expect($result['paths'])->toBe([])
        ->and($result['formatting_only'])->toBeTrue();
});

it('returns no paths for invalid json or scalar values', function (): void {
    $diff = new JsonPathDiff();

    expect($diff->diff('not json', '{"a":1}')['paths'])->toBe([])
        ->and($diff->diff('a', 'b')['paths'])->toBe([])
        ->and($diff->diff(null, null)['paths'])->toBe([]);
});

it('caps the reported paths at twenty', function (): void {
    $before = json_encode(array_fill(0, 30, 0));
    $after = json_encode(array_fill(0, 30, 1));

    $result = (new JsonPathDiff())->diff($before, $after);

    expect($result['paths'])->toHaveCount(20)
        ->and($result['truncated'])->toBeTrue();
});

it('falls back when the node budget is exceeded', function (): void {
    $before = json_encode(array_fill(0, 2500, 0));
    $after = json_encode(array_fill(0, 2500, 1));

    $result = (new JsonPathDiff())->diff($before, $after);

    expect($result['paths'])->toBe([])
        ->and($result['truncated'])->toBeTrue();
});

it('treats reordered object keys as formatting only', function (): void {
    $result = (new JsonPathDiff())->diff('{"a":1,"b":2}', '{"b":2,"a":1}');

    expect($result['paths'])->toBe([])
        ->and($result['formatting_only'])->toBeTrue();
});

it('reports a structured fallback for oversized json', function (): void {
    $result = (new JsonPathDiff())->diff('{"a":"'.str_repeat('x', 70000).'"}', '{"a":"'.str_repeat('y', 70000).'"}');

    expect($result['paths'])->toBe([])
        ->and($result['truncated'])->toBeTrue();
});
