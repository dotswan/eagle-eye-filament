<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Filament\Widgets\Overview;

use DateTimeImmutable;
use Dotswan\EagleEye\Data\Query\ActivityQuery;
use Dotswan\EagleEye\Enums\ActivitySeverity;
use Dotswan\EagleEyeFilament\Filament\Pages\UserActivityPage;
use Dotswan\EagleEyeFilament\Support\Stats\ActorRanking;
use Throwable;

final class OverviewBusiestActors extends OverviewSection
{
    protected string $view = 'eagle-eye-filament::widgets.overview.busiest-actors';

    public const int LIMIT = 5;

    /** @var list<array{actor: string, role: string, events: int, errors: ?int, url: ?string}> */
    private array $rows = [];

    private bool $resolved = false;

    private bool $failed = false;

    private bool $errorsAvailable = true;

    public function backendUnavailable(): bool
    {
        $this->authorizeAccess();
        $this->getRows();

        return $this->failed;
    }

    public function errorsAvailable(): bool
    {
        $this->authorizeAccess();

        $this->getRows();

        return $this->errorsAvailable;
    }

    /** @return list<array{actor: string, role: string, events: int, errors: ?int, url: ?string}> */
    public function getRows(): array
    {
        $this->authorizeAccess();

        if ($this->resolved) {
            return $this->rows;
        }

        $this->resolved = true;
        $range = $this->activityRange();
        [$from, $to] = $range->window();
        $formatter = $this->formatter();

        try {
            $ranking = app(ActorRanking::class)->top((new ActivityQuery())->between($from, $to), self::LIMIT);
        } catch (Throwable) {
            $this->failed = true;

            return $this->rows = [];
        }

        $errors = $this->errorCounts($from, $to);

        foreach ($ranking as $row) {
            $actor = $row['actor'];
            $url = null;

            if (null !== $actor->id && '' !== $actor->id) {
                $url = UserActivityPage::getUrl(['actor_type' => $actor->type, 'actor_id' => $actor->id]);
            }

            $this->rows[] = [
                'actor'  => $formatter->actorLabel($actor),
                'role'   => $actor->type,
                'events' => $row['events'],
                'errors' => $this->errorsAvailable ? ($errors[$row['key']] ?? 0) : null,
                'url'    => $url,
            ];
        }

        return $this->rows;
    }

    /**
     * @return array<string, int>
     */
    private function errorCounts(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        try {
            return $this->repository()->aggregate(
                (new ActivityQuery())
                    ->between($from, $to)
                    ->severities(
                        ActivitySeverity::Error,
                        ActivitySeverity::Critical,
                        ActivitySeverity::Alert,
                        ActivitySeverity::Emergency,
                    ),
                'actor',
            );
        } catch (Throwable) {
            $this->errorsAvailable = false;

            return [];
        }
    }
}
