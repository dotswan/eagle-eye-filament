@use('Dotswan\EagleEyeFilament\Filament\Widgets\Overview\OverviewTraffic')
@use('Dotswan\EagleEyeFilament\Filament\Widgets\Overview\OverviewFailures')
@use('Dotswan\EagleEyeFilament\Filament\Widgets\Overview\OverviewLatestRequests')
@use('Dotswan\EagleEyeFilament\Filament\Widgets\Overview\OverviewBusiestActors')
<x-filament-panels::page>
    @php($range = $this->getRange())

    <div class="ee ee-page">
        <x-eagle-eye-filament::page-shell
            active="overview"
            eyebrow="Activity Log"
            title="System overview"
            subtitle="Events, requests, errors and writes across the selected range."
        >
            <x-slot:actions>
                <x-eagle-eye-filament::segmented :items="$this->getRangeOptions()" />
            </x-slot:actions>

            @livewire(OverviewTraffic::class, ['range' => $range->value], key('overview-traffic-'.$range->value))
            @livewire(OverviewFailures::class, ['range' => $range->value], key('overview-failures-'.$range->value))
            <div class="ee-overview__row3">
                @livewire(OverviewLatestRequests::class, ['range' => $range->value], key('overview-latest-'.$range->value))
                @livewire(OverviewBusiestActors::class, ['range' => $range->value], key('overview-actors-'.$range->value))
            </div>
        </x-eagle-eye-filament::page-shell>
    </div>
</x-filament-panels::page>
