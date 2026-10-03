<?php

declare(strict_types=1);

use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Enums\ActivitySeverity;
use Dotswan\EagleEye\Facades\EagleEye;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function (): void {
    Gate::define('viewActivityLog', static fn (?object $viewer = null): bool => true);
    Gate::define('revealActivityLog', static fn (?object $viewer = null): bool => true);
});

function submitTokenSearch(string $input): ListActivityLogs
{
    $component = Livewire::test(ListActivityLogs::class)->set('searchInput', $input);
    $component->call('submitSearch');

    return explorerInstance($component->instance());
}

it('lifts recognized tokens into structured state', function (): void {
    EagleEye::fake();
    $instance = submitTokenSearch('channel:http severity:warning action:model.updated');

    expect($instance->channels)->toBe([ActivityChannel::Http->value])
        ->and($instance->severities)->toBe([ActivitySeverity::Warning->value])
        ->and($instance->actions)->toBe(['model.updated'])
        ->and($instance->q)->toBe('');
});

it('expands severity ranges into every matching severity', function (): void {
    EagleEye::fake();
    $instance = submitTokenSearch('severity:>=error');

    expect($instance->severities)->toBe(['error', 'critical', 'alert', 'emergency']);
});

it('keeps unknown keys and urls as free text', function (): void {
    EagleEye::fake();
    $instance = submitTokenSearch('http://example.com foo:bar hello world');

    expect($instance->q)->toBe('http://example.com foo:bar hello world')
        ->and($instance->channels)->toBe([]);
});

it('extracts quoted phrases into free text', function (): void {
    EagleEye::fake();
    $instance = submitTokenSearch('"exact phrase" channel:model');

    expect($instance->q)->toBe('exact phrase')
        ->and($instance->channels)->toBe([ActivityChannel::Model->value]);
});

it('normalizes subject tokens and keeps the last actor with a warning', function (): void {
    EagleEye::fake();
    $subject = submitTokenSearch('subject:CampaignRequest#42');

    expect($subject->subjectType)->toBe('campaign_request')
        ->and($subject->subjectId)->toBe('42');

    $actor = submitTokenSearch('actor:user:8821 actor:system');

    expect($actor->actorType)->toBe('system')
        ->and($actor->actorId)->toBeNull()
        ->and($actor->getSearchWarnings())->toContain('Multiple actor filters; using the last one.');
});

it('reports the token and character limits as inline errors', function (): void {
    EagleEye::fake();
    $manyTokens = submitTokenSearch(implode(' ', array_fill(0, 13, 'one')));

    expect($manyTokens->getSearchErrors())->toContain('Search accepts at most 12 tokens.');

    $longInput = submitTokenSearch(str_repeat('a', 257));

    expect($longInput->getSearchErrors())->toContain('Search input is limited to 256 characters.');
});
