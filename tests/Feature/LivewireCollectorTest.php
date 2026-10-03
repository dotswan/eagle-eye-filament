<?php

declare(strict_types=1);

use Dotswan\EagleEye\Data\ActivityDraft;
use Dotswan\EagleEye\Enums\ActivityChannel;
use Dotswan\EagleEyeFilament\Support\Livewire\LivewireInteractionCollector;
use Dotswan\EagleEyeFilament\Support\Livewire\LivewireRequestEnricher;
use Dotswan\EagleEyeFilament\Tests\Support\ThrowingLivewireComponent;
use Livewire\Livewire;

function livewireProbeComponent(string $name = 'edit-order'): object
{
    return new readonly class ($name) {
        public function __construct(private string $componentName)
        {
        }

        public function getName(): string
        {
            return $this->componentName;
        }

        public function getTitle(): string
        {
            return 'Orders';
        }
    };
}

it('collects calls and updated property names, ignoring noise methods', function (): void {
    $collector = new LivewireInteractionCollector();
    $component = livewireProbeComponent();

    $collector->recordCall($component, 'save');
    $collector->recordCall($component, '$refresh');
    $collector->recordUpdate($component, 'status');

    $snapshot = $collector->snapshot();

    if (! is_array($snapshot)) {
        throw new RuntimeException('Expected a collector snapshot.');
    }

    expect($snapshot['components'][0]['calls'])->toBe(['save'])
        ->and($snapshot['components'][0]['updates'])->toBe(['status'])
        ->and($snapshot['components'][0]['page'])->toBe('Orders')
        ->and($snapshot['quiet'])->toBeFalse();
});

it('marks a request as quiet when only ignored calls occur', function (): void {
    $collector = new LivewireInteractionCollector();
    $component = livewireProbeComponent();

    $collector->recordCall($component, '$refresh');

    expect($collector->snapshot()['quiet'] ?? null)->toBeTrue();
});

it('caps retained calls and reports truncation', function (): void {
    config()->set('eagle-eye-filament.livewire.max_calls', 2);
    $collector = new LivewireInteractionCollector();
    $component = livewireProbeComponent();

    foreach (['a', 'b', 'c', 'd'] as $method) {
        $collector->recordCall($component, $method);
    }

    $snapshot = $collector->snapshot();

    if (! is_array($snapshot)) {
        throw new RuntimeException('Expected a collector snapshot.');
    }

    expect($snapshot['components'][0]['calls'])->toHaveCount(2)
        ->and($snapshot['truncated'])->toBeTrue();
});

it('attaches the livewire payload to http request drafts and resets', function (): void {
    $collector = new LivewireInteractionCollector();
    $collector->recordCall(livewireProbeComponent(), 'save');

    $enricher = new LivewireRequestEnricher($collector);
    $draft = ActivityDraft::make('http.request', ActivityChannel::Http);
    $enricher->enrich($draft);

    expect($draft->payload['livewire']['components'])->toHaveCount(1)
        ->and($collector->snapshot())->toBeNull();
});

function livewireActionProbeComponent(): object
{
    return new class () {
        public function getName(): string
        {
            return 'edit-order';
        }

        public function getTitle(): string
        {
            return 'Orders';
        }

        /** @return array<int, array{name: string}> */
        public function mountedActions(): array
        {
            return [['name' => 'delete']];
        }

        public function getMountedAction(): object
        {
            return new class () {
                public function getName(): string
                {
                    return 'delete';
                }

                public function getLabel(): string
                {
                    return 'Delete';
                }
            };
        }
    };
}

it('records the mounted action label for mountAction and callMountedAction', function (): void {
    $collector = new LivewireInteractionCollector();
    $component = livewireActionProbeComponent();

    $collector->recordCall($component, 'mountAction', ['delete']);
    $collector->recordCall($component, 'callMountedAction', [['id' => 1]]);

    $snapshot = $collector->snapshot();

    if (! is_array($snapshot)) {
        throw new RuntimeException('Expected a collector snapshot.');
    }

    expect($snapshot['components'][0]['action'])->toBe(['name' => 'delete', 'label' => 'Delete']);
});

it('falls back to the mounted action name when no label is available', function (): void {
    $collector = new LivewireInteractionCollector();
    $component = new class () {
        public function getName(): string
        {
            return 'edit-order';
        }

        public function getTitle(): string
        {
            return 'Orders';
        }

        /** @var array<int, array{name: string}> */
        public array $mountedActions = [['name' => 'archive']];
    };

    $collector->recordCall($component, 'callMountedAction');

    $snapshot = $collector->snapshot();

    if (! is_array($snapshot)) {
        throw new RuntimeException('Expected a collector snapshot.');
    }

    expect($snapshot['components'][0]['action'])->toBe(['name' => 'archive', 'label' => 'archive']);
});

it('records a call even when the method throws a validation exception', function (): void {
    Livewire::test(ThrowingLivewireComponent::class)
        ->call('submit')
        ->assertHasErrors('email');

    $snapshot = app(LivewireInteractionCollector::class)->snapshot();

    if (! is_array($snapshot)) {
        throw new RuntimeException('Expected a collector snapshot.');
    }

    expect($snapshot['components'][0]['calls'])->toContain('submit');
});

it('keeps the action label across the mount and call lifecycle', function (): void {
    $component = new class () {
        public bool $mounted = false;

        public function getName(): string
        {
            return 'edit-order';
        }

        public function getMountedAction(): ?object
        {
            if (! $this->mounted) {
                return null;
            }

            return new class () {
                public function getName(): string
                {
                    return 'delete';
                }

                public function getLabel(): string
                {
                    return 'Delete';
                }
            };
        }
    };

    $collector = new LivewireInteractionCollector();

    $collector->recordCall($component, 'mountAction', ['delete']);
    $component->mounted = true;
    $collector->refreshAction($component, 'mountAction', ['delete']);

    $afterMount = $collector->snapshot();
    $collector->reset();

    $collector->recordCall($component, 'callMountedAction', [[]]);
    $component->mounted = false;
    $collector->refreshAction($component, 'callMountedAction', [[]]);

    $afterCall = $collector->snapshot();

    if (! is_array($afterMount) || ! is_array($afterCall)) {
        throw new RuntimeException('Expected collector snapshots.');
    }

    expect($afterMount['components'][0]['action'])->toBe(['name' => 'delete', 'label' => 'Delete'])
        ->and($afterCall['components'][0]['action'])->toBe(['name' => 'delete', 'label' => 'Delete']);
});
