<?php

declare(strict_types=1);

use Dotswan\EagleEyeFilament\Support\Presentation\ChangeRowsBuilder;
use Dotswan\EagleEyeFilament\Support\Presentation\EntryFormatter;
use Dotswan\EagleEyeFilament\Support\Presentation\JsonPathDiff;

function changeRowsBuilder(): ChangeRowsBuilder
{
    return new ChangeRowsBuilder(new EntryFormatter(), new JsonPathDiff());
}

it('builds scalar change rows', function (): void {
    $rows = changeRowsBuilder()->build([
        ['path' => 'status', 'label' => 'Status', 'before' => 'draft', 'after' => 'published'],
    ]);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['label'])->toBe('Status')
        ->and($rows[0]['kind'])->toBe('scalar')
        ->and($rows[0]['before'])->toBe("'draft'")
        ->and($rows[0]['after'])->toBe("'published'")
        ->and($rows[0]['paths'])->toBe([]);
});

it('builds structured path rows for json columns', function (): void {
    $rows = changeRowsBuilder()->build([
        ['path' => 'process_steps', 'label' => 'Process steps', 'before' => '{"a":1,"b":2}', 'after' => '{"a":1,"b":3}'],
    ]);

    expect($rows[0]['kind'])->toBe('structured')
        ->and($rows[0]['paths'])->toBe([['path' => 'b', 'before' => '2', 'after' => '3']]);
});

it('ignores malformed change entries', function (): void {
    expect(changeRowsBuilder()->build(['nope', ['path' => 'x', 'label' => 'X', 'before' => 1, 'after' => 2]]))
        ->toHaveCount(1);
});
