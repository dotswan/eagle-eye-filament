<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Support\Presentation;

use JsonException;

final class JsonPathDiff
{
    public const int MAX_PATHS = 20;

    public const int MAX_DECODE_BYTES = 65536;

    public const int MAX_DEPTH = 8;

    public const int MAX_NODES = 2000;

    /**
     * @return array{paths: list<array{path: string, before: mixed, after: mixed}>, formatting_only: bool, truncated: bool}
     */
    public function diff(mixed $before, mixed $after): array
    {
        $beforeTree = $this->decode($before);
        $afterTree = $this->decode($after);

        if (! $beforeTree['structured'] || ! $afterTree['structured']) {
            return [
                'paths'           => [],
                'formatting_only' => false,
                'truncated'       => $beforeTree['candidate'] || $afterTree['candidate'],
            ];
        }

        $beforeFlat = $this->flatten($beforeTree['value']);
        $afterFlat = $this->flatten($afterTree['value']);

        if (null === $beforeFlat || null === $afterFlat) {
            return ['paths' => [], 'formatting_only' => false, 'truncated' => true];
        }

        $paths = [];
        $total = 0;

        foreach (array_unique([...array_keys($beforeFlat), ...array_keys($afterFlat)]) as $path) {
            $old = $beforeFlat[$path] ?? null;
            $new = $afterFlat[$path] ?? null;

            if ($old === $new) {
                continue;
            }

            $total++;

            if (count($paths) < self::MAX_PATHS) {
                $paths[] = ['path' => $path, 'before' => $old, 'after' => $new];
            }
        }

        $formattingOnly = 0 === $total
            && $beforeTree['raw'] !== $afterTree['raw']
            && $this->canonical($beforeFlat) === $this->canonical($afterFlat);

        return [
            'paths'           => $paths,
            'formatting_only' => $formattingOnly,
            'truncated'       => $total > self::MAX_PATHS,
        ];
    }

    /** @param array<string, mixed> $flat */
    private function canonical(array $flat): string
    {
        ksort($flat);

        return serialize($flat);
    }

    /**
     * @return array{structured: bool, value: mixed, raw: string, candidate: bool}
     */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return ['structured' => true, 'value' => $value, 'raw' => $this->encode($value), 'candidate' => true];
        }

        if (! is_string($value)) {
            return ['structured' => false, 'value' => $value, 'raw' => $this->encode($value), 'candidate' => false];
        }

        $candidate = $this->looksStructured($value);

        if (mb_strlen($value, '8bit') > self::MAX_DECODE_BYTES) {
            return ['structured' => false, 'value' => $value, 'raw' => $value, 'candidate' => $candidate];
        }

        if (! $candidate) {
            return ['structured' => false, 'value' => $value, 'raw' => $value, 'candidate' => false];
        }

        try {
            $decoded = json_decode($value, true, self::MAX_DEPTH + 1, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['structured' => false, 'value' => $value, 'raw' => $value, 'candidate' => true];
        }

        if (! is_array($decoded)) {
            return ['structured' => false, 'value' => $value, 'raw' => $value, 'candidate' => true];
        }

        return ['structured' => true, 'value' => $decoded, 'raw' => $value, 'candidate' => true];
    }

    private function looksStructured(string $value): bool
    {
        $trimmed = mb_trim($value);

        return '' !== $trimmed && (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '['));
    }

    /**
     * @return array<string, mixed>|null null when the node or depth budget is exceeded
     */
    private function flatten(mixed $value, string $prefix = '', int $depth = 0): ?array
    {
        if ($depth > self::MAX_DEPTH) {
            return null;
        }

        if (is_array($value) && [] !== $value) {
            $out = [];

            foreach ($value as $key => $item) {
                $path = '' === $prefix ? (string) $key : $prefix.'.'.$key;
                $nested = $this->flatten($item, $path, $depth + 1);

                if (null === $nested) {
                    return null;
                }

                foreach ($nested as $nestedPath => $nestedValue) {
                    $out[$nestedPath] = $nestedValue;
                }

                if (count($out) > self::MAX_NODES) {
                    return null;
                }
            }

            return $out;
        }

        return ['' === $prefix ? '(value)' : $prefix => $value];
    }

    private function encode(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return serialize($value);
        }
    }
}
