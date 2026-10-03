<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Support\Livewire;

use Dotswan\EagleEye\Contracts\Pipeline\DraftEnricherInterface;
use Dotswan\EagleEye\Data\ActivityDraft;
use Dotswan\EagleEye\Enums\ActivityChannel;

final readonly class LivewireRequestEnricher implements DraftEnricherInterface
{
    public function __construct(private LivewireInteractionCollector $collector)
    {
    }

    public function enrich(ActivityDraft $draft): void
    {
        if (ActivityChannel::Http !== $draft->channel || 'http.request' !== $draft->action) {
            return;
        }

        $snapshot = $this->collector->snapshot();

        if (null === $snapshot) {
            return;
        }

        $draft->payload['livewire'] = $snapshot;

        foreach (array_slice($snapshot['components'], 0, 3) as $component) {
            $draft->withTags(['livewire:component:'.$component['name']]);
        }

        $this->collector->reset();
    }
}
