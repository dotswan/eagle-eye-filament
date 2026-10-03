<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Filament\Widgets;

use Dotswan\EagleEye\Contracts\Repository\ActivityRepositoryInterface;
use Dotswan\EagleEye\Contracts\Resolution\ActivityEntryPresenterInterface;
use Dotswan\EagleEye\Contracts\Security\LogAccessPolicyInterface;
use Dotswan\EagleEye\Data\ActivityEntry;
use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEyeFilament\Filament\Pages\SubjectHistoryPage;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;

final class ActivityTimelineWidget extends Widget
{
    #[Locked]
    public ?string $subjectType = null;

    #[Locked]
    public ?string $subjectId = null;

    public int $limit = 10;

    public static function canView(): bool
    {
        return app(LogAccessPolicyInterface::class)->canView(auth()->user());
    }

    public function render(): View
    {
        abort_unless(self::canView(), 403);

        return app(Factory::class)->make('eagle-eye-filament::widgets.activity-timeline');
    }

    /** @return list<ActivityEntry> */
    public function getEntries(): array
    {
        abort_unless(self::canView(), 403);
        $query = (new ActivityQuery())->limit(max(1, min($this->limit, 100)));

        if (null !== $this->subjectType && null !== $this->subjectId) {
            abort_unless(
                '' !== $this->subjectType && mb_strlen($this->subjectType) <= 256 && 0 === preg_match('/[\x00-\x1F\x7F]/', $this->subjectType)
                && '' !== $this->subjectId && mb_strlen($this->subjectId) <= 256 && 0 === preg_match('/[\x00-\x1F\x7F]/', $this->subjectId),
                422,
            );
            $query->forSubject($this->subjectType, $this->subjectId);
        }

        return app(ActivityEntryPresenterInterface::class)->present(
            app(ActivityRepositoryInterface::class)->search($query)->entries,
        );
    }

    public function getEntryUrl(ActivityEntry $entry): string
    {
        abort_unless(self::canView(), 403);

        return ActivityLogResource::getUrl('view', ['record' => $entry->id]);
    }

    public function getSubjectHistoryUrl(): ?string
    {
        abort_unless(self::canView(), 403);

        if (! $this->hasSubject()) {
            return null;
        }

        return SubjectHistoryPage::getUrl(['subject_type' => $this->subjectType, 'subject_id' => $this->subjectId]);
    }

    private function hasSubject(): bool
    {
        return is_string($this->subjectType) && '' !== $this->subjectType && mb_strlen($this->subjectType) <= 256 && 0 === preg_match('/[\x00-\x1F\x7F]/', $this->subjectType)
            && is_string($this->subjectId) && '' !== $this->subjectId && mb_strlen($this->subjectId) <= 256 && 0 === preg_match('/[\x00-\x1F\x7F]/', $this->subjectId);
    }
}
