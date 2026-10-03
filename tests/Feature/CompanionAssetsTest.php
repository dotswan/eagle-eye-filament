<?php

declare(strict_types=1);

use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEye\Enums\ActivitySeverity;
use Dotswan\EagleEyeFilament\Tests\Support\EntryFactory;
use Filament\Support\Facades\FilamentAsset;
use Dotswan\EagleEyeFilament\EagleEyeFilamentServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

it('registers the companion stylesheet with filament assets', function (): void {
    $ids = array_map(
        static fn (object $asset): string => $asset->getId(),
        FilamentAsset::getStyles(),
    );

    expect($ids)->toContain('eagle-eye-filament');
});

it('registers the config, views and translations publish tags', function (): void {
    $targets = [
        'eagle-eye-filament-config'       => config_path('eagle-eye-filament.php'),
        'eagle-eye-filament-views'        => resource_path('views/vendor/eagle-eye-filament'),
        'eagle-eye-filament-translations' => lang_path('vendor/eagle-eye-filament'),
    ];

    foreach ($targets as $tag => $target) {
        $paths = ServiceProvider::pathsToPublish(EagleEyeFilamentServiceProvider::class, $tag);

        expect(array_values($paths))->toBe([$target]);
    }
});

it('renders the anonymous components and escapes entry data', function (): void {
    $chip = Blade::render('<x-eagle-eye-filament::chip tone="blue">http</x-eagle-eye-filament::chip>');
    $card = Blade::render('<x-eagle-eye-filament::card heading="<script>alert(1)</script>">body</x-eagle-eye-filament::card>');

    expect($chip)->toContain('ee-chip--blue')
        ->and($card)->toContain('&lt;script&gt;')
        ->and($card)->not->toContain('<script>');
});

it('renders an entry row with channel severity and actor labels', function (): void {
    $entry = EntryFactory::make(
        '01J0000000000000000000E1',
        str_repeat('e', 32),
        channel: ActivityChannel::Model,
        severity: ActivitySeverity::Warning,
    );

    $html = Blade::render(
        '<x-eagle-eye-filament::entry-row :entry="$entry" />',
        ['entry' => $entry],
    );

    expect($html)->toContain('ee-chip--neutral')
        ->and($html)->toContain('ee-chip--amber')
        ->and($html)->toContain('model')
        ->and($html)->toContain('warning');
});

it('compiles every anonymous component', function (): void {
    $data = [
        'segments'    => [['label' => 'One', 'href' => '/one', 'active' => true], ['label' => 'Two', 'wire' => 'go']],
        'diff'        => [['label' => 'Status', 'before' => "'a'", 'after' => "'b'"]],
        'json'        => '{}',
        'barSegments' => [['tone' => 'red', 'percent' => 40, 'label' => 'errors']],
        'values'      => [1, 5, 2],
        'crumbs'      => [['label' => 'Explorer', 'href' => '/logs'], ['label' => 'id']],
        'tabs'        => [['key' => 'explorer', 'label' => 'Explorer', 'href' => '/logs']],
    ];

    $templates = [
        '<x-eagle-eye-filament::card heading="Head" subheading="Sub">body</x-eagle-eye-filament::card>',
        '<x-eagle-eye-filament::segmented :items="$segments" />',
        '<x-eagle-eye-filament::chip tone="red" outline>error</x-eagle-eye-filament::chip>',
        '<x-eagle-eye-filament::stat-grid variant="cards"><x-eagle-eye-filament::stat size="kpi" label="Events" :value="3" unit="today" delta="+1" delta-tone="good" /></x-eagle-eye-filament::stat-grid>',
        '<x-eagle-eye-filament::kv-row label="IP" value="127.0.0.1" mono />',
        '<x-eagle-eye-filament::diff-table :rows="$diff" />',
        '<x-eagle-eye-filament::diff-table variant="stacked" :rows="$diff" />',
        '<x-eagle-eye-filament::code-block :code="$json" meta="128 B" copy />',
        '<x-eagle-eye-filament::bar-stack :segments="$barSegments" label="Mix" />',
        '<x-eagle-eye-filament::sparkline :values="$values" tone="green" label="Trend" />',
        '<x-eagle-eye-filament::empty message="Nothing here." />',
        '<x-eagle-eye-filament::breadcrumb :items="$crumbs" />',
        '<x-eagle-eye-filament::nav-tabs active="explorer" :items="$tabs" />',
        '<x-eagle-eye-filament::facet label="http" value="http" :count="3" active />',
        '<x-eagle-eye-filament::page-shell active="overview" eyebrow="Activity Log" title="System overview">body</x-eagle-eye-filament::page-shell>',
    ];

    foreach ($templates as $template) {
        expect(Blade::render($template, $data))->toBeString();
    }
});
