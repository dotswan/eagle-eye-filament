<?php

declare(strict_types=1);

use Dotswan\EagleEye\Data\ActivityEntry;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Facades\EagleEye;
use Dotswan\EagleEyeFilament\Support\Presentation\EntryDetailPresenter;

beforeEach(function (): void {
    Gate::define('viewActivityLog', static fn (?object $viewer = null): bool => true);
    Gate::define('revealActivityLog', static fn (?object $viewer = null): bool => true);
});

/** @return array{title: string, rows: list<array{label: string, value: string, mono: bool, type?: string, href?: string, truncated?: bool}>}|null */
function detailSection(ActivityEntry $entry, string $title): ?array
{
    $sections = app(EntryDetailPresenter::class)->sections($entry);

    foreach ($sections as $section) {
        if ($title === $section['title']) {
            return $section;
        }
    }

    return null;
}

/** @param array{title: string, rows: list<array{label: string, value: string, mono: bool, type?: string, href?: string, truncated?: bool}>}|null $section */
function sectionHas(?array $section, string $label, string $value): bool
{
    if (null === $section) {
        return false;
    }

    foreach ($section['rows'] as $row) {
        if (str_starts_with($row['label'], $label) && str_contains($row['value'], $value)) {
            return true;
        }
    }

    return false;
}

it('renders http request details', function (): void {
    EagleEye::fake();
    $entry = companionEntry('01J0000000000000000000D1', 'Http', ActivityChannel::Http, payload: [
        'method'       => 'GET',
        'route'        => 'orders.index',
        'status'       => 200,
        'duration_ms'  => 120,
        'title_source' => ['params' => ['page' => 'Orders']],
    ]);

    $section = detailSection($entry, 'Request');

    expect($section)->not->toBeNull()
        ->and(sectionHas($section, 'Page', 'Orders'))->toBeTrue()
        ->and(sectionHas($section, 'Duration', '120'))->toBeTrue();
});

it('renders mail details', function (): void {
    EagleEye::fake();
    $entry = companionEntry('01J0000000000000000000D2', 'Mail', ActivityChannel::Mail, payload: [
        'subject'  => 'Your login code',
        'to'       => ['a***@dotswan.com'],
        'to_count' => 1,
    ]);

    $section = detailSection($entry, 'Mail');

    expect(sectionHas($section, 'Subject', 'login code'))->toBeTrue();
});

it('renders outbound response body details', function (): void {
    EagleEye::fake();
    $entry = companionEntry('01J0000000000000000000D3', 'Outbound', ActivityChannel::OutboundHttp, payload: [
        'method'        => 'POST',
        'host'          => 'api.example.com',
        'path'          => '/v1/orders',
        'status'        => 500,
        'response_body' => '{"error":"boom"}',
    ]);

    expect(detailSection($entry, 'Outbound request'))->not->toBeNull()
        ->and(detailSection($entry, 'Response body'))->not->toBeNull();
});

it('renders livewire interaction details', function (): void {
    EagleEye::fake();
    $entry = companionEntry('01J0000000000000000000D4', 'Livewire', ActivityChannel::Http, payload: [
        'livewire' => [
            'components' => [[
                'name'    => 'edit-order',
                'page'    => 'Orders',
                'calls'   => ['save', 'refresh'],
                'updates' => ['status'],
                'action'  => ['label' => 'Save'],
            ]],
            'quiet'     => false,
            'truncated' => false,
        ],
    ]);

    $section = detailSection($entry, 'Livewire');

    expect($section)->not->toBeNull()
        ->and(sectionHas($section, 'edit-order', 'Save'))->toBeTrue();
});

it('renders model state change details', function (): void {
    EagleEye::fake();
    $entry = companionEntry('01J0000000000000000000D5', 'State', ActivityChannel::Model, payload: [
        'state' => ['field' => 'status', 'old' => 'pending', 'new' => 'failed'],
    ]);

    $section = detailSection($entry, 'State change');

    expect($section)->not->toBeNull()
        ->and(sectionHas($section, 'To', 'failed'))->toBeTrue();
});

it('renders opt-in http bodies and query keys', function (): void {
    EagleEye::fake();
    $entry = companionEntry('01J0000000000000000000D6', 'Http body', ActivityChannel::Http, payload: [
        'method'   => 'POST',
        'route'    => 'orders.store',
        'status'   => 201,
        'request'  => ['body' => ['status' => 'draft'], 'query' => ['tab' => '1'], 'route_params' => ['order' => '42']],
        'response' => ['status' => 201, 'body' => ['id' => 12]],
    ]);

    $requestBody = detailSection($entry, 'Request body');
    $responseBody = detailSection($entry, 'Response body');
    $request = detailSection($entry, 'Request');

    expect(sectionHas($requestBody, 'Body', 'draft'))->toBeTrue()
        ->and(sectionHas($responseBody, 'Body', '12'))->toBeTrue()
        ->and(sectionHas($request, 'Query keys', 'tab'))->toBeTrue()
        ->and(sectionHas($request, 'Route params', 'order=42'))->toBeTrue();
});

it('renders outbound response bodies decoded as json', function (): void {
    EagleEye::fake();
    $entry = companionEntry('01J0000000000000000000D7', 'Outbound body', ActivityChannel::OutboundHttp, payload: [
        'host'          => 'api.example.com',
        'path'          => '/v1/orders',
        'status'        => 500,
        'response_body' => ['error' => 'boom'],
    ]);

    expect(sectionHas(detailSection($entry, 'Response body'), 'Body', '"error": "boom"'))->toBeTrue();
});

it('renders command argument pairs, job short names, and relative exception locations', function (): void {
    EagleEye::fake();
    $command = companionEntry('01J0000000000000000000D8', 'Command', ActivityChannel::Console, payload: [
        'command'   => 'reports:build',
        'arguments' => ['name' => 'weekly'],
        'options'   => ['force' => true],
    ]);
    $job = companionEntry('01J0000000000000000000D9', 'Job', ActivityChannel::Queue, payload: [
        'job' => 'App\\Jobs\\GenerateReportJob',
    ]);
    $exception = companionEntry('01J0000000000000000000DA', 'Exception', ActivityChannel::Exception, payload: [
        'exception' => RuntimeException::class,
        'message'   => 'boom',
        'file'      => base_path().'/modules/Example/Service.php',
        'line'      => 42,
    ]);

    expect(sectionHas(detailSection($command, 'Command'), 'Arguments', 'name=weekly'))->toBeTrue()
        ->and(sectionHas(detailSection($command, 'Command'), 'Options', 'force=true'))->toBeTrue()
        ->and(sectionHas(detailSection($job, 'Job'), 'Job', 'GenerateReport'))->toBeTrue()
        ->and(sectionHas(detailSection($job, 'Job'), 'Class', 'App\\Jobs\\GenerateReportJob'))->toBeTrue()
        ->and(sectionHas(detailSection($exception, 'Exception'), 'Location', 'modules/Example/Service.php:42'))->toBeTrue();
});

it('labels new query summaries and legacy slowest lists distinctly', function (): void {
    EagleEye::fake();
    $nested = companionEntry('01J0000000000000000000DB', 'Nested', ActivityChannel::Http, payload: [
        'queries' => [
            'count'      => 30,
            'total_ms'   => 7.42,
            'nested'     => ['count' => 12, 'total_ms' => 3.1],
            'slow'       => [['sql' => 'select 1', 'ms' => 612.4]],
            'duplicates' => [['sql' => 'select 2', 'count' => 14, 'total_ms' => 2.9]],
            'truncated'  => false,
        ],
    ]);
    $legacy = companionEntry('01J0000000000000000000DC', 'Legacy', ActivityChannel::Http, payload: [
        'queries' => ['count' => 2, 'total_ms' => 2, 'slowest' => [['sql' => 'select 3', 'ms' => 3]]],
    ]);

    $queries = detailSection($nested, 'Queries');

    expect(sectionHas($queries, 'Nested', '3.10 ms'))->toBeTrue()
        ->and(sectionHas($queries, 'Slow', '612.40 ms'))->toBeTrue()
        ->and(sectionHas($queries, 'Repeated', '14×'))->toBeTrue()
        ->and(sectionHas(detailSection($legacy, 'Queries'), 'Top queries by time', '3.00 ms'))->toBeTrue();
});

it('renders array values as json instead of casting to string', function (): void {
    EagleEye::fake();
    $entry = companionEntry('01J0000000000000000000DD', 'Array job', ActivityChannel::Queue, payload: [
        'job' => ['name' => 'Nested', 'args' => [1, 2]],
    ]);

    expect(sectionHas(detailSection($entry, 'Job'), 'Class', 'Nested'))->toBeTrue();
});

it('falls back to english detail labels for an unknown locale', function (): void {
    EagleEye::fake();
    app()->setLocale('xx');
    $entry = companionEntry('01J0000000000000000000DF', 'Locale', ActivityChannel::Http, payload: ['method' => 'GET']);

    expect(detailSection($entry, 'Request'))->not->toBeNull()
        ->and(sectionHas(detailSection($entry, 'Request'), 'Method', 'GET'))->toBeTrue();
});

it('renders a notification subject as a link', function (): void {
    EagleEye::fake();
    $entry = companionEntry('01J0000000000000000000E0', 'Notification', ActivityChannel::Notification, payload: [
        'notification' => 'OrderShipped',
    ], subjectType: 'order', subjectId: '01J0000000000000000000E1', subjectLabel: 'Order #7');

    $row = null;

    foreach (detailSection($entry, 'Notification')['rows'] ?? [] as $candidate) {
        if ('Subject' === $candidate['label']) {
            $row = $candidate;
        }
    }

    expect($row['type'] ?? null)->toBe('link')
        ->and($row['href'] ?? null)->not->toBeNull()
        ->and($row['value'] ?? null)->toBe('Order #7');
});

it('keeps a sibling directory outside the app root absolute', function (): void {
    EagleEye::fake();
    $entry = companionEntry('01J0000000000000000000E2', 'Sibling', ActivityChannel::Exception, payload: [
        'exception' => RuntimeException::class,
        'message'   => 'boom',
        'file'      => base_path().'2/Other/Service.php',
        'line'      => 9,
    ]);

    expect(sectionHas(detailSection($entry, 'Exception'), 'Location', base_path().'2/Other/Service.php:9'))->toBeTrue();
});

it('shows the slow query threshold in the label', function (): void {
    EagleEye::fake();
    config()->set('eagle-eye.capture.query.slow_threshold_ms', 750);
    $entry = companionEntry('01J0000000000000000000E3', 'Slow', ActivityChannel::Http, payload: [
        'queries' => ['count' => 1, 'total_ms' => 800.0, 'slow' => [['sql' => 'select 1', 'ms' => 800.0]]],
    ]);

    $queries = detailSection($entry, 'Queries');
    $row = null;

    foreach ($queries['rows'] ?? [] as $candidate) {
        if (str_starts_with($candidate['label'], 'Slow')) {
            $row = $candidate;
        }
    }

    expect($row['label'] ?? null)->toBe('Slow (≥ 750 ms)');
});
