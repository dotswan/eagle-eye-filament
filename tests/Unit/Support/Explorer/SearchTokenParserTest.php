<?php

declare(strict_types=1);

use Dotswan\EagleEyeFilament\Support\Explorer\SearchTokenParser;

it('lifts recognized keys into structured criteria', function (): void {
    $parsed = (new SearchTokenParser())->parse('channel:http severity:warning action:model.updated');

    expect($parsed->criteria['channels'])->toBe(['http'])
        ->and($parsed->criteria['severities'])->toBe(['warning'])
        ->and($parsed->criteria['actions'])->toBe(['model.updated'])
        ->and($parsed->freeText)->toBe('')
        ->and($parsed->errors)->toBe([]);
});

it('expands severity ranges', function (): void {
    $parser = new SearchTokenParser();

    expect($parser->parse('severity:>=error')->criteria['severities'])
        ->toBe(['error', 'critical', 'alert', 'emergency'])
        ->and($parser->parse('severity:>=warning')->criteria['severities'])
        ->toBe(['warning', 'error', 'critical', 'alert', 'emergency']);
});

it('keeps unknown keys and urls as free text', function (): void {
    $parsed = (new SearchTokenParser())->parse('http://example.com foo:bar hello world');

    expect($parsed->freeText)->toBe('http://example.com foo:bar hello world')
        ->and($parsed->criteria['channels'])->toBe([]);
});

it('handles quoted phrases as free text', function (): void {
    $parsed = (new SearchTokenParser())->parse('"exact phrase" extra');

    expect($parsed->freeText)->toBe('exact phrase extra');
});

it('normalizes subject studly case and captures ids', function (): void {
    $parser = new SearchTokenParser();

    expect($parser->parse('subject:CampaignRequest')->criteria['subjectType'])->toBe('campaign_request')
        ->and($parser->parse('subject:CampaignRequest#42')->criteria['subjectType'])->toBe('campaign_request')
        ->and($parser->parse('subject:CampaignRequest#42')->criteria['subjectId'])->toBe('42')
        ->and($parser->parse('subject:Modules\\CampaignRequest\\Models\\CampaignRequest')->criteria['subjectType'])->toBe('campaign_request');
});

it('parses actor filters and warns on the last wins', function (): void {
    $parser = new SearchTokenParser();
    $parsed = $parser->parse('actor:user:8821 actor:system');

    expect($parsed->criteria['actorType'])->toBe('system')
        ->and($parsed->criteria['actorId'])->toBeNull()
        ->and($parsed->warnings)->toContain('Multiple actor filters; using the last one.')
        ->and($parsed->errors)->toBe([]);
});

it('rejects unknown enum values and invalid traces', function (): void {
    $parser = new SearchTokenParser();

    expect($parser->parse('severity:erorr')->errors)->toContain("Unknown activity severity 'erorr'.")
        ->and($parser->parse('trace:zzz')->errors)->toContain("Unknown trace filter 'zzz'.")
        ->and($parser->parse('action:bad/value')->errors)->toContain("Unknown activity action 'bad/value'.");
});

it('caps tokens and characters', function (): void {
    $parser = new SearchTokenParser();
    $manyTokens = $parser->parse(implode(' ', array_fill(0, 13, 'one')));
    $longInput = $parser->parse(str_repeat('a', 257));

    expect($manyTokens->errors)->toContain('Search accepts at most 12 tokens.')
        ->and($longInput->errors)->toContain('Search input is limited to 256 characters.');
});
