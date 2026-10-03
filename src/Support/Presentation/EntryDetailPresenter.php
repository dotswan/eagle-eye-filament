<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Support\Presentation;

use Dotswan\EagleEye\Data\Subject;
use Dotswan\EagleEye\Data\ActivityEntry;
use Dotswan\EagleEye\Data\RequestSnapshot;
use Dotswan\EagleEye\Enums\ActivityChannel;

final readonly class EntryDetailPresenter
{
    public function __construct(private EntryFormatter $formatter)
    {
    }

    /**
     * @return list<array{title: string, rows: list<array{label: string, value: string, mono: bool, type?: string, href?: string, truncated?: bool}>}>
     */
    public function sections(ActivityEntry $entry): array
    {
        $sections = match ($entry->channel) {
            ActivityChannel::Http         => $this->http($entry),
            ActivityChannel::Mail         => $this->mail($entry),
            ActivityChannel::Notification => $this->notification($entry),
            ActivityChannel::OutboundHttp => $this->outbound($entry),
            ActivityChannel::Query        => $this->query($entry),
            ActivityChannel::Queue        => $this->job($entry),
            ActivityChannel::Console      => $this->command($entry),
            ActivityChannel::Exception    => $this->exception($entry),
            ActivityChannel::Model        => $this->model($entry),
            default                       => [],
        };

        if (ActivityChannel::Http !== $entry->channel) {
            return [...$sections, ...$this->genericRequest($entry)];
        }

        return $sections;
    }

    /** @return list<array{title: string, rows: list<array{label: string, value: string, mono: bool, type?: string, href?: string, truncated?: bool}>}> */
    private function genericRequest(ActivityEntry $entry): array
    {
        $request = $entry->request;

        if (! $request instanceof RequestSnapshot) {
            return [];
        }

        if (null === $request->method && null === $request->url && null === $request->route
            && null === $request->statusCode && null === $request->durationMs) {
            return [];
        }

        $rows = [];
        $this->add($rows, $this->label('method'), $request->method, true);
        $this->add($rows, $this->label('url'), $request->url, true);
        $this->add($rows, $this->label('route'), $request->route, true);
        $this->add($rows, $this->label('status'), $request->statusCode, true);
        $this->add($rows, $this->label('duration'), $this->duration($request->durationMs), true);
        $this->add($rows, $this->label('ip'), $request->ip, true);
        $this->add($rows, $this->label('user_agent'), $request->userAgent);

        return [] === $rows ? [] : [['title' => $this->section('generic_request'), 'rows' => $rows]];
    }

    /** @return list<array{title: string, rows: list<array{label: string, value: string, mono: bool, type?: string, href?: string, truncated?: bool}>}> */
    private function http(ActivityEntry $entry): array
    {
        $payload = $entry->payload;
        $request = $entry->request;
        $sections = [];

        $rows = [];
        $this->add($rows, $this->label('method'), $payload['method'] ?? $request?->method, true);
        $this->add($rows, $this->label('route'), $payload['route'] ?? $request?->route, true);
        $this->add($rows, $this->label('url'), $request?->url, true);
        $this->add($rows, $this->label('page'), $this->titleParam($payload, 'page'));
        $this->add($rows, $this->label('resource'), $this->titleParam($payload, 'resource_label'));
        $this->add($rows, $this->label('status'), $payload['status'] ?? $request?->statusCode, true);
        $this->add($rows, $this->label('duration'), $this->duration($payload['duration_ms'] ?? $request?->durationMs), true);
        $this->add($rows, $this->label('ip'), $payload['ip'] ?? $request?->ip, true);
        $this->add($rows, $this->label('user_agent'), $payload['user_agent'] ?? $request?->userAgent);
        $this->add($rows, $this->label('query_keys'), $this->list(array_keys($this->map($payload['request']['query'] ?? null))), true);
        $this->add($rows, $this->label('route_params'), $this->pairs($payload['request']['route_params'] ?? null), true);

        if ([] !== $rows) {
            $sections[] = ['title' => $this->section('generic_request'), 'rows' => $rows];
        }

        $body = $this->map($payload['request']['body'] ?? null);
        $row = $this->codeRow($this->label('body'), $body);

        if (null !== $row) {
            $sections[] = ['title' => $this->section('request_body'), 'rows' => [$row]];
        }

        $responseBody = $payload['response']['body'] ?? null;
        $row = $this->codeRow($this->label('body'), $responseBody);

        if (null !== $row) {
            $sections[] = ['title' => $this->section('response_body'), 'rows' => [$row]];
        }

        $sections = [...$sections, ...$this->livewire($payload)];

        return [...$sections, ...$this->queries($payload)];
    }

    /** @return list<array{title: string, rows: list<array{label: string, value: string, mono: bool, type?: string, href?: string, truncated?: bool}>}> */
    private function mail(ActivityEntry $entry): array
    {
        $payload = $entry->payload;
        $rows = [];
        $this->add($rows, $this->label('subject'), $payload['subject'] ?? null);
        $this->add($rows, $this->label('recipients'), $this->list($payload['to'] ?? null));
        $this->add($rows, $this->label('to_count'), $payload['to_count'] ?? null, true);
        $this->add($rows, $this->label('cc_count'), $payload['cc_count'] ?? null, true);
        $this->add($rows, $this->label('bcc_count'), $payload['bcc_count'] ?? null, true);
        $this->add($rows, $this->label('domains'), $this->list($payload['to_domains'] ?? null));
        $this->add($rows, $this->label('mailer'), $payload['mailer'] ?? null, true);
        $this->add($rows, $this->label('message_id'), $payload['message_id'] ?? null, true);
        $this->add($rows, $this->label('error'), $payload['error'] ?? null);

        return [] === $rows ? [] : [['title' => $this->section('mail'), 'rows' => $rows]];
    }

    /** @return list<array{title: string, rows: list<array{label: string, value: string, mono: bool, type?: string, href?: string, truncated?: bool}>}> */
    private function notification(ActivityEntry $entry): array
    {
        $payload = $entry->payload;
        $rows = [];
        $this->add($rows, $this->label('notification'), $payload['notification_short'] ?? $payload['notification'] ?? null, true);
        $this->add($rows, $this->label('channel'), $payload['channel'] ?? null, true);
        $this->add($rows, $this->label('notifiable'), $entry->subject?->label);
        $subject = $entry->subject;
        $url = $this->subjectUrl($entry);

        if ($subject instanceof Subject && null !== $url && '' !== $url) {
            $label = null !== $subject->label && '' !== $subject->label
                ? $subject->label
                : $subject->type.' #'.$this->formatter->shortKey($subject->id);
            $rows[] = ['label' => $this->label('subject'), 'value' => $label, 'mono' => false, 'type' => 'link', 'href' => $url];
        } else {
            $this->add($rows, $this->label('subject'), $subject?->label);
        }

        $this->add($rows, $this->label('error'), $payload['error'] ?? null);

        return [] === $rows ? [] : [['title' => $this->section('notification'), 'rows' => $rows]];
    }

    /** @return list<array{title: string, rows: list<array{label: string, value: string, mono: bool, type?: string, href?: string, truncated?: bool}>}> */
    private function outbound(ActivityEntry $entry): array
    {
        $payload = $entry->payload;
        $rows = [];
        $this->add($rows, $this->label('method'), $payload['method'] ?? null, true);
        $this->add($rows, $this->label('host'), $payload['host'] ?? null, true);
        $this->add($rows, $this->label('path'), $payload['path'] ?? null, true);
        $this->add($rows, $this->label('query_keys'), $this->list($payload['query_keys'] ?? null), true);
        $this->add($rows, $this->label('status'), $payload['status'] ?? null, true);
        $this->add($rows, $this->label('duration'), $this->duration($payload['duration_ms'] ?? null), true);
        $this->add($rows, $this->label('response_bytes'), $payload['response_bytes'] ?? null, true);
        $this->add($rows, $this->label('error'), $payload['error'] ?? null);

        $sections = [] === $rows ? [] : [['title' => $this->section('outbound_request'), 'rows' => $rows]];

        $row = $this->codeRow($this->label('body'), $payload['response_body'] ?? null);

        if (null !== $row) {
            $sections[] = ['title' => $this->section('response_body'), 'rows' => [$row]];
        }

        return $sections;
    }

    /** @return list<array{title: string, rows: list<array{label: string, value: string, mono: bool, type?: string, href?: string, truncated?: bool}>}> */
    private function query(ActivityEntry $entry): array
    {
        $payload = $entry->payload;
        $rows = [];
        $this->add($rows, $this->label('sql'), $payload['sql'] ?? null, true);
        $this->add($rows, $this->label('connection'), $payload['connection'] ?? null, true);
        $this->add($rows, $this->label('duration'), $this->duration($payload['duration_ms'] ?? null), true);
        $this->add($rows, $this->label('bindings'), $payload['bindings_count'] ?? null, true);

        return [] === $rows ? [] : [['title' => $this->section('query'), 'rows' => $rows]];
    }

    /** @return list<array{title: string, rows: list<array{label: string, value: string, mono: bool, type?: string, href?: string, truncated?: bool}>}> */
    private function job(ActivityEntry $entry): array
    {
        $payload = $entry->payload;
        $rows = [];
        $job = $payload['job'] ?? null;
        $this->add($rows, $this->label('job'), is_string($job) ? $this->shortClass($job) : $job, true);
        $this->add($rows, $this->label('class'), $job, true);
        $this->add($rows, $this->label('queue'), $payload['queue'] ?? null, true);
        $this->add($rows, $this->label('connection'), $payload['connection'] ?? null, true);
        $this->add($rows, $this->label('attempts'), $payload['attempts'] ?? null, true);
        $this->add($rows, $this->label('duration'), $this->duration($payload['duration_ms'] ?? null), true);
        $this->add($rows, $this->label('uuid'), $payload['job_uuid'] ?? $payload['uuid'] ?? null, true);
        $this->add($rows, $this->label('exception'), $payload['exception'] ?? null, true);
        $this->add($rows, $this->label('message'), $payload['error'] ?? $payload['message'] ?? null);

        $sections = [] === $rows ? [] : [['title' => $this->section('job'), 'rows' => $rows]];

        return [...$sections, ...$this->queries($payload)];
    }

    /** @return list<array{title: string, rows: list<array{label: string, value: string, mono: bool, type?: string, href?: string, truncated?: bool}>}> */
    private function command(ActivityEntry $entry): array
    {
        $payload = $entry->payload;
        $rows = [];
        $this->add($rows, $this->label('command'), $payload['command'] ?? null, true);
        $this->add($rows, $this->label('arguments'), $this->pairs($payload['arguments'] ?? null), true);
        $this->add($rows, $this->label('options'), $this->pairs($payload['options'] ?? null), true);
        $this->add($rows, $this->label('exit_code'), $payload['exit_code'] ?? null, true);
        $this->add($rows, $this->label('duration'), $this->duration($payload['duration_ms'] ?? null), true);
        $this->add($rows, $this->label('memory_peak'), isset($payload['memory_peak_mb']) ? $payload['memory_peak_mb'].' MB' : null, true);

        $sections = [] === $rows ? [] : [['title' => $this->section('command'), 'rows' => $rows]];

        return [...$sections, ...$this->queries($payload)];
    }

    /** @return list<array{title: string, rows: list<array{label: string, value: string, mono: bool, type?: string, href?: string, truncated?: bool}>}> */
    private function exception(ActivityEntry $entry): array
    {
        $payload = $entry->payload;
        $rows = [];
        $this->add($rows, $this->label('class'), $payload['exception'] ?? $payload['class'] ?? null, true);
        $this->add($rows, $this->label('message'), $payload['message'] ?? null);
        $this->add($rows, $this->label('location'), $this->location($payload), true);

        return [] === $rows ? [] : [['title' => $this->section('exception'), 'rows' => $rows]];
    }

    /** @return list<array{title: string, rows: list<array{label: string, value: string, mono: bool, type?: string, href?: string, truncated?: bool}>}> */
    private function model(ActivityEntry $entry): array
    {
        $state = $entry->payload['state'] ?? null;

        if (! is_array($state)) {
            return [];
        }

        $rows = [];
        $this->add($rows, $this->label('field'), $state['field'] ?? null, true);
        $this->add($rows, $this->label('from'), $state['old'] ?? null);
        $this->add($rows, $this->label('to'), $state['new'] ?? null);

        return [] === $rows ? [] : [['title' => $this->section('state_change'), 'rows' => $rows]];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{title: string, rows: list<array{label: string, value: string, mono: bool, type?: string, href?: string, truncated?: bool}>}>
     */
    private function livewire(array $payload): array
    {
        $livewire = $payload['livewire'] ?? null;

        if (! is_array($livewire) || ! is_array($livewire['components'] ?? null)) {
            return [];
        }

        $rows = [];

        foreach ($livewire['components'] as $component) {
            if (! is_array($component)) {
                continue;
            }

            $name = (string) ($component['name'] ?? '');
            $methods = $this->list($component['calls'] ?? null);
            $properties = $this->list($component['updates'] ?? null);
            $action = $component['action']['label'] ?? null;
            $page = $component['page'] ?? null;

            $rows[] = ['label' => $name, 'value' => mb_trim(implode(' · ', array_filter([
                $page,
                $action,
                '' !== $methods ? 'calls: '.$methods : null,
                '' !== $properties ? 'updates: '.$properties : null,
            ]))), 'mono' => true];
        }

        return [] === $rows ? [] : [['title' => $this->section('livewire'), 'rows' => $rows]];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{title: string, rows: list<array{label: string, value: string, mono: bool, type?: string, href?: string, truncated?: bool}>}>
     */
    private function queries(array $payload): array
    {
        $queries = $payload['queries'] ?? null;

        if (! is_array($queries)) {
            return [];
        }

        $rows = [];
        $this->add($rows, $this->label('queries'), $this->queryTotals($queries), true);

        if (is_array($queries['nested'] ?? null)) {
            $this->add($rows, $this->label('nested'), $this->queryTotals($queries['nested']), true);
        }

        if (array_key_exists('slow', $queries)) {
            $slow = is_array($queries['slow']) ? $queries['slow'] : [];

            if ([] === $slow) {
                $this->add($rows, $this->slowLabel(), $this->label('none'), true);
            }

            foreach ($slow as $entry) {
                if (is_array($entry)) {
                    $this->add($rows, $this->slowLabel(), $this->queryLine($entry['sql'] ?? null, $entry['ms'] ?? null), true);
                }
            }
        }

        foreach (($queries['duplicates'] ?? []) as $entry) {
            if (is_array($entry)) {
                $count = is_int($entry['count'] ?? null) ? $entry['count'].'× ' : '';
                $this->add($rows, $this->label('repeated'), $count.$this->queryLine($entry['sql'] ?? null, $entry['total_ms'] ?? null), true);
            }
        }

        foreach (($queries['slowest'] ?? []) as $entry) {
            if (is_array($entry)) {
                $this->add($rows, $this->label('top_queries'), $this->queryLine($entry['sql'] ?? null, $entry['ms'] ?? null), true);
            }
        }

        return [] === $rows ? [] : [['title' => $this->section('queries'), 'rows' => $rows]];
    }

    /** @param array<string, mixed> $totals */
    private function queryTotals(array $totals): string
    {
        $count = is_int($totals['count'] ?? null) ? (string) $totals['count'] : '?';
        $milliseconds = $this->milliseconds($totals['total_ms'] ?? null);

        return null === $milliseconds ? $count : $count.' · '.$milliseconds;
    }

    private function queryLine(mixed $sql, mixed $milliseconds): string
    {
        $value = is_string($sql) ? $sql : '';
        $duration = $this->milliseconds($milliseconds);

        return null === $duration ? $value : $value.' · '.$duration;
    }

    private function milliseconds(mixed $value): ?string
    {
        if (! is_int($value) && ! is_float($value) && (!is_string($value) || !is_numeric($value))) {
            return null;
        }

        return number_format((float) $value, 2).' ms';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function titleParam(array $payload, string $key): ?string
    {
        $params = $payload['title_source']['params'] ?? null;

        if (! is_array($params) || ! isset($params[$key])) {
            return null;
        }

        return (string) $params[$key];
    }

    private function duration(mixed $milliseconds): ?string
    {
        if (! is_int($milliseconds) && ! is_numeric($milliseconds)) {
            return null;
        }

        return $this->formatter->durationLabel((int) $milliseconds);
    }

    private function list(mixed $value): string
    {
        if (! is_array($value) || [] === $value) {
            return '';
        }

        return implode(', ', array_map(static function (mixed $item): string {
            if (is_scalar($item)) {
                return (string) $item;
            }

            $json = json_encode($item, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

            return is_string($json) ? $json : '';
        }, $value));
    }

    /** @return array<array-key, mixed> */
    private function map(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function pairs(mixed $value): ?string
    {
        $parts = [];

        foreach ($this->map($value) as $key => $item) {
            if (is_scalar($item) || null === $item) {
                $parts[] = $key.'='.$this->scalar($item);
            }
        }

        return [] === $parts ? null : implode(' ', $parts);
    }

    private function scalar(mixed $value): string
    {
        if (null === $value) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function jsonBlock(mixed $value): ?string
    {
        if (is_string($value)) {
            return '' === $value ? null : $value;
        }

        if (! is_array($value) || [] === $value) {
            return null;
        }

        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return is_string($json) ? $json : null;
    }

    private function shortClass(string $class): string
    {
        $short = class_basename($class);

        return str_ends_with($short, 'Job') ? mb_substr($short, 0, -3) : $short;
    }

    private function subjectUrl(ActivityEntry $entry): ?string
    {
        return ActivityNav::subjectUrl($entry->subject);
    }

    /** @param array<string, mixed> $payload */
    private function location(array $payload): ?string
    {
        $file = $payload['file'] ?? null;

        if (! is_string($file) || '' === $file) {
            return null;
        }

        $prefix = mb_rtrim(base_path(), '/').'/';
        $relative = str_starts_with($file, $prefix) ? mb_substr($file, mb_strlen($prefix)) : $file;
        $line = $payload['line'] ?? null;

        return is_int($line) || (is_string($line) && '' !== $line) ? $relative.':'.$line : $relative;
    }

    /**
     * @param  list<array{label: string, value: string, mono: bool}>  $rows
     */
    private function add(array &$rows, string $label, mixed $value, bool $mono = false): void
    {
        if (null === $value || '' === $value || [] === $value) {
            return;
        }

        if (is_scalar($value)) {
            $rendered = (string) $value;
        } elseif (is_array($value)) {
            $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            $rendered = is_string($json) ? $this->formatter->value($json) : '';
        } else {
            return;
        }

        if ('' === $rendered) {
            return;
        }

        $rows[] = ['label' => $label, 'value' => $rendered, 'mono' => $mono];
    }

    /** @return array{label: string, value: string, mono: bool, type: string, truncated: bool}|null */
    private function codeRow(string $label, mixed $value): ?array
    {
        if (is_string($value)) {
            $text = $value;
        } elseif (is_array($value) && [] !== $value) {
            $text = $this->jsonBlock($value) ?? '';
        } else {
            return null;
        }

        if ('' === $text) {
            return null;
        }

        $kb = number_format(mb_strlen($text, '8bit') / 1024, 1);

        return [
            'label'     => $label.' ('.$kb.' KB)',
            'value'     => $this->formatter->value($text),
            'mono'      => true,
            'type'      => 'code',
            'truncated' => $this->formatter->isTruncated($text),
        ];
    }

    private function label(string $key): string
    {
        return (string) __('eagle-eye-filament::detail.labels.'.$key);
    }

    private function section(string $key): string
    {
        return (string) __('eagle-eye-filament::detail.sections.'.$key);
    }

    private function slowLabel(): string
    {
        $threshold = max(0, (int) config('eagle-eye.capture.query.slow_threshold_ms', 500));

        return (string) __('eagle-eye-filament::detail.labels.slow', ['ms' => $threshold]);
    }
}
