<?php

declare(strict_types=1);

function eeStylesheet(): string
{
    return (string) file_get_contents(dirname(__DIR__, 3).'/resources/dist/eagle-eye-filament.css');
}

it('defines every non-dynamic ee class used in the blade views and php sources', function (): void {
    $root = dirname(__DIR__, 3);
    $css = eeStylesheet();
    $used = [];

    foreach (['/resources/views', '/src'] as $directory) {
        $views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.$directory));

        foreach ($views as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $name = $file->getFilename();

            if (! str_ends_with($name, '.blade.php') && ! str_ends_with($name, '.php')) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            preg_match_all('/\bee-[A-Za-z0-9_-]+/', $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$token, $offset]) {
                if (str_ends_with($token, '-') || str_ends_with($token, '--')) {
                    continue;
                }

                if ('--' === mb_strcut($contents, max(0, $offset - 2), 2)) {
                    continue;
                }

                $prefix = mb_strcut($contents, max(0, $offset - 16), 16);

                if (
                    str_contains($prefix, 'livewire:')
                    || str_contains($prefix, 'wire:')
                    || str_contains($prefix, 'dispatch(')
                    || str_contains($prefix, 'component(')
                    || str_contains($prefix, "On('")
                    || str_contains($prefix, 'x-on:')
                    || str_contains($prefix, '@js(')
                ) {
                    continue;
                }

                $used[$token] = true;
            }
        }
    }

    $undefined = array_values(array_filter(
        array_keys($used),
        static fn (string $class): bool => 1 !== preg_match('/\.'.preg_quote($class, '/').'(?![\w-])/', $css),
    ));

    sort($undefined);

    expect($undefined)->toBe([]);
});

it('defines every dynamic tone class rendered by the components', function (): void {
    $css = eeStylesheet();
    $channelTones = ['blue', 'violet', 'amber', 'green', 'gray', 'cyan', 'teal', 'sky', 'red', 'indigo', 'slate', 'neutral'];
    $severityTones = ['gray', 'blue', 'amber', 'red', 'rose'];

    $groups = [
        'ee-chip--'                => array_values(array_unique([...$channelTones, ...$severityTones, 'action', 'tag'])),
        'ee-tabs__dot--'           => ['blue', 'green', 'amber', 'violet', 'red', 'neutral'],
        'ee-group__span--'         => $channelTones,
        'ee-waterfall__bar--'      => $channelTones,
        'ee-trace-row__duration--' => ['amber', 'neutral'],
        'ee-status-dot--'          => ['green', 'amber', 'red', 'gray'],
        'ee-stat--'                => ['kpi', 'summary', 'meta'],
        'ee-tone--'                => $channelTones,
    ];

    $missing = [];

    foreach ($groups as $prefix => $tones) {
        foreach ($tones as $tone) {
            $class = $prefix.$tone;

            if (1 !== preg_match('/\.'.preg_quote($class, '/').'(?![\w-])/', $css)) {
                $missing[] = $class;
            }
        }
    }

    expect($missing)->toBe([]);
});

it('never declares the same top-level selector twice', function (): void {
    $css = (string) preg_replace('/\/\*.*?\*\//s', '', eeStylesheet());
    $selectors = [];
    $buffer = '';
    $depth = 0;
    $length = mb_strlen($css);

    for ($index = 0; $index < $length; $index++) {
        $character = $css[$index];

        if ('{' === $character) {
            $selector = mb_trim($buffer);

            if (0 === $depth && '' !== $selector && ! str_starts_with($selector, '@')) {
                $selectors[$selector] = ($selectors[$selector] ?? 0) + 1;
            }

            $depth++;
            $buffer = '';
        } elseif ('}' === $character) {
            $depth = max(0, $depth - 1);
            $buffer = '';
        } elseif (0 === $depth) {
            $buffer .= $character;
        }
    }

    $duplicates = array_keys(array_filter($selectors, static fn (int $count): bool => $count > 1));

    expect($duplicates)->toBe([]);
});

it('never relies on css variable fallback lists for fonts', function (): void {
    $css = eeStylesheet();

    expect($css)->not->toContain('var(--mono-font-family,')
        ->and($css)->not->toContain('var(--font-family,')
        ->and($css)->toContain('--ee-font-mono: var(--mono-font-family),')
        ->and($css)->toContain('--ee-font-sans: var(--font-family),');
});
