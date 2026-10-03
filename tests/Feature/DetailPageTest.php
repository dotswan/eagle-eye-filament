<?php

declare(strict_types=1);

use Dotswan\EagleEye\Contracts\Repository\ActivityRepositoryInterface;
use Dotswan\EagleEye\Data\ActivityDraft;
use Dotswan\EagleEye\Data\ActivityEntry;
use Dotswan\EagleEye\Data\Query\QueryResult;
use Dotswan\EagleEye\Data\RequestSnapshot;
use Dotswan\EagleEye\Data\SecretRef;
use Dotswan\EagleEye\Data\TraceContext;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Exceptions\SecretRevealDeniedException;
use Dotswan\EagleEye\Facades\EagleEye;
use Dotswan\EagleEyeFilament\Filament\Pages\SubjectHistoryPage;
use Dotswan\EagleEyeFilament\Filament\Pages\UserActivityPage;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource\Pages\ViewActivityLog;
use Dotswan\EagleEyeFilament\Support\Presentation\EntryFormatter;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function (): void {
    Gate::define('viewActivityLog', static fn (?object $viewer = null): bool => true);
    Gate::define('revealActivityLog', static fn (?object $viewer = null): bool => true);
});

it('links to time-ordered trace neighbours and disables the ends', function (): void {
    EagleEye::fake();
    $trace = str_repeat('c', 32);
    $at = new DateTimeImmutable('-10 minutes', new DateTimeZone('UTC'));
    $first = companionEntry(sprintf('01J%021d', 200_001), 'Trace first', loggedAt: $at, traceId: $trace);
    $middle = companionEntry(sprintf('01J%021d', 200_002), 'Trace middle', loggedAt: $at->modify('+1 second'), traceId: $trace);
    $last = companionEntry(sprintf('01J%021d', 200_003), 'Trace last', loggedAt: $at->modify('+2 seconds'), traceId: $trace);

    $firstComponent = Livewire::test(ViewActivityLog::class, ['record' => $first->id]);
    $lastComponent = Livewire::test(ViewActivityLog::class, ['record' => $last->id]);
    $middleUrl = ActivityLogResource::getUrl('view', ['record' => $middle->id]);

    expect(detailInstance($firstComponent->instance())->getNeighbors())->toBe(['prev' => null, 'next' => $middleUrl])
        ->and(detailInstance($lastComponent->instance())->getNeighbors())->toBe(['prev' => $middleUrl, 'next' => null]);

    $firstComponent->assertSee('aria-disabled="true"', false)->assertSee($middleUrl);
    $lastComponent->assertSee('aria-disabled="true"', false);
});

it('hides neighbour navigation when the entry is outside the trace window', function (): void {
    EagleEye::fake(2000);
    $trace = str_repeat('d', 32);
    $at = new DateTimeImmutable('-90 minutes', new DateTimeZone('UTC'));
    $ids = [];

    for ($sequence = 1; $sequence <= 51; $sequence++) {
        $ids[$sequence] = companionEntry(
            sprintf('01J%021d', 210_000 + $sequence),
            'Trace window '.$sequence,
            loggedAt: $at->modify('+'.$sequence.' seconds'),
            traceId: $trace,
        )->id;
    }

    $component = Livewire::test(ViewActivityLog::class, ['record' => $ids[51]]);
    $instance = detailInstance($component->instance());

    expect($instance->traceWindowExceeded())->toBeTrue()
        ->and($instance->getNeighbors())->toBe(['prev' => null, 'next' => null]);

    $component->assertSee('Trace has more than 50 entries')
        ->assertDontSee('aria-disabled="true"', false);
});

it('lists related subject entries excluding the current event', function (): void {
    EagleEye::fake();
    $at = new DateTimeImmutable('-10 minutes', new DateTimeZone('UTC'));
    $self = companionEntry(sprintf('01J%021d', 220_001), 'Subject self', loggedAt: $at->modify('+10 seconds'), subjectType: 'order', subjectId: '555');

    for ($sequence = 1; $sequence <= 5; $sequence++) {
        companionEntry(
            sprintf('01J%021d', 220_010 + $sequence),
            'Subject sibling '.$sequence,
            loggedAt: $at->modify('+'.$sequence.' seconds'),
            subjectType: 'order',
            subjectId: '555',
        );
    }

    $component = Livewire::test(ViewActivityLog::class, ['record' => $self->id]);
    $related = detailInstance($component->instance())->getRelatedSubjectEntries();
    $ids = array_map(static fn (ActivityEntry $entry): string => $entry->id, $related);

    expect($related)->toHaveCount(4)
        ->and($ids)->not->toContain($self->id);
});

it('hides the same-subject section without a subject', function (): void {
    EagleEye::fake();
    $id = sprintf('01J%021d', 230_001);
    companionEntry($id, 'No subject event', subjectType: null);

    Livewire::test(ViewActivityLog::class, ['record' => $id])->assertDontSee('Same subject');
});

it('shows the request card only when request details exist', function (): void {
    EagleEye::fake();
    $withRequest = companionEntry(
        sprintf('01J%021d', 240_001),
        'HTTP capture',
        request: new RequestSnapshot('GET', 'https://example.test/orders', 'orders.show', 200, 42, '127.0.0.1'),
    );
    $ipOnly = companionEntry(
        sprintf('01J%021d', 240_002),
        'Auth capture',
        request: new RequestSnapshot(ip: '127.0.0.1'),
    );

    Livewire::test(ViewActivityLog::class, ['record' => $withRequest->id])
        ->assertSee('Request')
        ->assertSee('orders.show')
        ->assertSee('GET');

    Livewire::test(ViewActivityLog::class, ['record' => $ipOnly->id])
        ->assertDontSee('Request');
});

it('reveals a sealed value when the policy allows it', function (): void {
    EagleEye::fake();
    $entry = companionEntry(sprintf('01J%021d', 250_001), 'Sealed value', secrets: ['token' => new SecretRef('aes-256-gcm', 'fingerprint', 'ciphertext')]);

    $repository = Mockery::mock(ActivityRepositoryInterface::class);
    $repository->shouldReceive('find')->andReturn($entry);
    $repository->shouldReceive('search')->andReturn(QueryResult::empty());
    $repository->shouldReceive('reveal')->once()->andReturn('plaintext-token');
    app()->instance(ActivityRepositoryInterface::class, $repository);

    $component = Livewire::test(ViewActivityLog::class, ['record' => $entry->id])
        ->call('revealSecret', 'token');

    expect(detailInstance($component->instance())->getRevealed('token'))->toBe('plaintext-token');
    $component->assertSee('plaintext-token');
});

it('keeps a sealed value hidden when reveal is denied', function (): void {
    EagleEye::fake();
    $entry = companionEntry(sprintf('01J%021d', 250_002), 'Sealed denied', secrets: ['token' => new SecretRef('aes-256-gcm', 'fingerprint', 'ciphertext')]);

    $repository = Mockery::mock(ActivityRepositoryInterface::class);
    $repository->shouldReceive('find')->andReturn($entry);
    $repository->shouldReceive('search')->andReturn(QueryResult::empty());
    $repository->shouldReceive('reveal')->once()->andThrow(new SecretRevealDeniedException($entry->id, 'token'));
    app()->instance(ActivityRepositoryInterface::class, $repository);

    $component = Livewire::test(ViewActivityLog::class, ['record' => $entry->id])
        ->call('revealSecret', 'token');

    expect(detailInstance($component->instance())->getRevealed('token'))->toBeNull();
});

it('returns no plaintext when the reveal audit fails', function (): void {
    EagleEye::fake();
    $entry = companionEntry(sprintf('01J%021d', 250_003), 'Sealed audit failure', secrets: ['token' => new SecretRef('aes-256-gcm', 'fingerprint', 'ciphertext')]);

    $repository = Mockery::mock(ActivityRepositoryInterface::class);
    $repository->shouldReceive('find')->andReturn($entry);
    $repository->shouldReceive('search')->andReturn(QueryResult::empty());
    $repository->shouldReceive('reveal')->once()->andThrow(new RuntimeException('audit unavailable'));
    app()->instance(ActivityRepositoryInterface::class, $repository);

    $component = Livewire::test(ViewActivityLog::class, ['record' => $entry->id]);
    $instance = detailInstance($component->instance());

    expect($instance->getRevealed('token'))->toBeNull()
        ->and(fn () => $instance->revealSecret('token'))->toThrow(RuntimeException::class)
        ->and($instance->getRevealed('token'))->toBeNull();
});

it('truncates long change values and offers the full value', function (): void {
    EagleEye::fake();
    $id = sprintf('01J%021d', 260_001);
    $long = str_repeat('a', 2500);
    companionEntry($id, 'Long value', payload: ['changes' => ['note' => ['old' => 'short', 'new' => $long]]]);

    $component = Livewire::test(ViewActivityLog::class, ['record' => $id]);
    $rows = detailInstance($component->instance())->getChangeRows();

    expect($rows)->toHaveCount(1)
        ->and(mb_strlen($rows[0]['after']))->toBe(EntryFormatter::MAX_VALUE_LENGTH + 1)
        ->and($rows[0]['afterFull'])->toBeString()
        ->and(mb_strlen((string) $rows[0]['afterFull']))->toBe(2502);

    $component->assertSee('show all');
});

it('encodes payload json containing invalid utf-8 without throwing', function (): void {
    EagleEye::fake();
    $id = sprintf('01J%021d', 270_001);
    $draft = ActivityDraft::make('recorded')->withPayload(['bad' => "\xB1\x31"]);
    $draft->id = $id;
    $draft->title = 'Invalid utf8';
    $draft->trace = new TraceContext(str_repeat('a', 32), $id);
    $entry = $draft->freeze();

    $repository = Mockery::mock(ActivityRepositoryInterface::class);
    $repository->shouldReceive('find')->andReturn($entry);
    $repository->shouldReceive('search')->andReturn(QueryResult::empty());
    app()->instance(ActivityRepositoryInterface::class, $repository);

    $component = Livewire::test(ViewActivityLog::class, ['record' => $id]);

    expect(detailInstance($component->instance())->getPayloadJson())->toContain('bad');
    $component->assertOk();
});

it('escapes an attacker controlled title', function (): void {
    EagleEye::fake();
    $id = sprintf('01J%021d', 280_001);
    companionEntry($id, '<script>alert(1)</script>');

    Livewire::test(ViewActivityLog::class, ['record' => $id])
        ->assertSee('<script>alert(1)</script>')
        ->assertDontSee('<script>', false);
});

it('links to the actor profile and the subject history page', function (): void {
    EagleEye::fake();
    $entry = companionEntry(sprintf('01J%021d', 300_001), 'Actor link');

    $component = Livewire::test(ViewActivityLog::class, ['record' => $entry->id]);
    $instance = detailInstance($component->instance());

    expect($instance->getActorUrl())->toBe(UserActivityPage::getUrl(['actor_type' => 'user', 'actor_id' => '8821']))
        ->and($instance->getSubjectUrl())->toBe(SubjectHistoryPage::getUrl(['subject_type' => 'order', 'subject_id' => '77213']));

    $component->assertSee('View actor profile')
        ->assertSee('Full record history');
});

it('rejects a detail lookup that returns a different activity id', function (): void {
    EagleEye::fake();
    $requestedId = '01J0000000000000000000K1';
    $collision = companionEntry('01J0000000000000000000K2', 'References '.$requestedId);
    $repository = Mockery::mock(ActivityRepositoryInterface::class);

    if (! $repository instanceof ActivityRepositoryInterface) {
        throw new LogicException('Activity repository mock unavailable.');
    }

    $repository->shouldReceive('find')->once()->with($requestedId)->andReturn($collision);
    app()->instance(ActivityRepositoryInterface::class, $repository);

    Livewire::test(ViewActivityLog::class, ['record' => $requestedId])
        ->assertNotFound();
});

it('reauthorizes trace data after access is revoked', function (): void {
    EagleEye::fake();
    $trace = str_repeat('e', 32);
    $id = sprintf('01J%021d', 290_001);
    companionEntry($id, 'Revoke trace', traceId: $trace);
    companionEntry(sprintf('01J%021d', 290_002), 'Revoke trace sibling', traceId: $trace);

    $component = Livewire::test(ViewActivityLog::class, ['record' => $id]);

    Gate::define('viewActivityLog', static fn (?object $viewer = null): bool => false);

    $component->call('getTrace')->assertForbidden();
});

it('denies the detail page when access is unavailable', function (): void {
    Gate::define('viewActivityLog', static fn (?object $viewer = null): bool => false);

    Livewire::test(ViewActivityLog::class, ['record' => sprintf('01J%021d', 310_001)])->assertForbidden();
});

it('omits the changes card for entries without changes', function (): void {
    EagleEye::fake();
    $id = sprintf('01J%021d', 280_001);
    companionEntry($id, 'Http probe', ActivityChannel::Http);

    Livewire::test(ViewActivityLog::class, ['record' => $id])
        ->assertDontSee('No attribute changes recorded');
});

it('renders json path diffs instead of raw blocks', function (): void {
    EagleEye::fake();
    $id = sprintf('01J%021d', 280_002);
    companionEntry($id, 'Json change', payload: [
        'changes' => ['process_steps' => ['old' => '{"a":1,"b":2}', 'new' => '{"a":1,"b":3}']],
    ]);

    $component = Livewire::test(ViewActivityLog::class, ['record' => $id]);
    $rows = detailInstance($component->instance())->getChangeRows();

    expect($rows[0]['kind'])->toBe('structured')
        ->and($rows[0]['paths'])->toBe([['path' => 'b', 'before' => '2', 'after' => '3']]);

    $component->assertDontSee('{"a":1,"b":2}');
});

it('hides the internal title source from the rendered payload', function (): void {
    EagleEye::fake();
    $id = sprintf('01J%021d', 280_003);
    companionEntry($id, 'Title source probe', payload: [
        'title_source' => ['key' => 'titles.http.request'],
        'note'         => 'visible',
    ]);

    $component = Livewire::test(ViewActivityLog::class, ['record' => $id]);
    $json = detailInstance($component->instance())->getPayloadJson();

    expect($json)->not->toContain('title_source')
        ->and($json)->toContain('visible');
});

it('renders request and response bodies in collapsed code blocks', function (): void {
    EagleEye::fake();
    $entry = companionEntry('01J0000000000000000000F1', 'Body', ActivityChannel::Http, payload: [
        'method'   => 'POST',
        'route'    => 'orders.store',
        'status'   => 201,
        'request'  => ['body' => ['draft' => str_repeat('x', 3000)]],
        'response' => ['status' => 201, 'body' => ['id' => 12]],
    ]);

    $component = Livewire::test(ViewActivityLog::class, ['record' => $entry->id]);

    $component->assertSee('<details', false)
        ->assertSee('Truncated to fit the capture limit.');
});
