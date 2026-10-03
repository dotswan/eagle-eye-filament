<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Support\Presentation;

final readonly class ChangeRowsBuilder
{
    public function __construct(
        private EntryFormatter $formatter,
        private JsonPathDiff $diff,
    ) {
    }

    /**
     * @param  array<int, mixed>  $changes  the `changes` list from the core ActivityEntryResource
     * @return list<array{label: string, kind: string, before: string, after: string, beforeFull: ?string, afterFull: ?string, formattingOnly: bool, truncated: bool, paths: list<array{path: string, before: string, after: string}>}>
     */
    public function build(array $changes): array
    {
        $rows = [];

        foreach ($changes as $change) {
            if (! is_array($change)) {
                continue;
            }

            $before = $change['before'] ?? null;
            $after = $change['after'] ?? null;
            $diff = $this->diff->diff($before, $after);
            $structured = [] !== $diff['paths'] || $diff['truncated'];

            $rows[] = [
                'label'          => (string) ($change['label'] ?? $change['path'] ?? ''),
                'kind'           => $structured ? 'structured' : 'scalar',
                'before'         => $this->formatter->value($before),
                'after'          => $this->formatter->value($after),
                'beforeFull'     => $this->formatter->isTruncated($before) ? $this->formatter->fullValue($before) : null,
                'afterFull'      => $this->formatter->isTruncated($after) ? $this->formatter->fullValue($after) : null,
                'formattingOnly' => $diff['formatting_only'],
                'truncated'      => $diff['truncated'],
                'paths'          => array_map(fn (array $path): array => [
                    'path'   => (string) $path['path'],
                    'before' => $this->formatter->value($path['before']),
                    'after'  => $this->formatter->value($path['after']),
                ], $diff['paths']),
            ];
        }

        return $rows;
    }
}
