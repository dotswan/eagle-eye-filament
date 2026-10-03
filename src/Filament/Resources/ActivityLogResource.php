<?php

declare(strict_types=1);

namespace Dotswan\EagleEyeFilament\Filament\Resources;

use BackedEnum;
use Dotswan\EagleEye\Contracts\Security\LogAccessPolicyInterface;
use Dotswan\EagleEyeFilament\EagleEyeFilamentPlugin;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use Dotswan\EagleEyeFilament\Filament\Resources\ActivityLogResource\Pages\ViewActivityLog;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class ActivityLogResource extends Resource
{
    protected static ?string $model = null;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-eye';

    protected static ?string $navigationLabel = 'Activity Log';

    protected static ?string $slug = 'activity-logs';

    protected static ?int $navigationSort = 0;

    /** @return class-string<Model> */
    public static function getModel(): string
    {
        return Model::class;
    }

    /**
     * Authorization flows through LogAccessPolicyInterface (enforced in the
     * pages and navigation), not through Eloquent model policies.
     */
    public static function shouldSkipAuthorization(): bool
    {
        return true;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return EagleEyeFilamentPlugin::current()?->getNavigationGroup() ?? parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        $sort = EagleEyeFilamentPlugin::current()?->getNavigationSort();

        return null === $sort ? parent::getNavigationSort() : $sort + 1;
    }

    public static function getModelLabel(): string
    {
        return 'activity entry';
    }

    public static function getPluralModelLabel(): string
    {
        return 'activity entries';
    }

    public static function canViewAny(): bool
    {
        /** @var object|null $viewer */
        $viewer = auth()->user();

        return app(LogAccessPolicyInterface::class)->canView($viewer);
    }

    /** @return array<string, \Filament\Resources\Pages\PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
            'view'  => ViewActivityLog::route('/{record}'),
        ];
    }
}
