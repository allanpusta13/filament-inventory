<?php

declare(strict_types=1);

namespace App\Filament\Resources\InTransits;

use App\Enums\InTransitStatus;
use App\Filament\Resources\InTransits\Pages\ListInTransits;
use App\Filament\Resources\InTransits\Pages\ViewInTransit;
use App\Filament\Resources\InTransits\Schemas\InTransitInfolist;
use App\Filament\Resources\InTransits\Tables\InTransitsTable;
use App\Filament\Support\Concerns\ScopesNavigationBadges;
use App\Models\InTransit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * InTransit resource — §18.1a canonical class.
 *
 * Read-only monitor. Badge-bearing (§1B.3a). No create, no edit.
 */
class InTransitResource extends Resource
{
    use ScopesNavigationBadges;

    protected static ?string $model = InTransit::class;

    protected static string|UnitEnum|null $navigationGroup = 'OPERATIONS';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Truck;

    public static function getModelLabel(): string
    {
        return __('resources.in_transits.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.in_transits.model.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.in_transits.navigation.label');
    }

    public static function table(Table $table): Table
    {
        return InTransitsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InTransitInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['transferRequisition', 'transferRequisitionItem', 'productVariant'])
            ->when(
                ! auth()->user()->isAdmin() && ! auth()->user()->isAuditor(),
                function (Builder $q) {
                    $ids = auth()->user()->warehouses()->pluck('id')->all();
                    $q->whereHas('transferRequisition', function (Builder $qq) use ($ids) {
                        $qq->whereIn('from_warehouse_id', $ids)
                            ->orWhereIn('to_warehouse_id', $ids);
                    });
                }
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInTransits::route('/'),
            'view' => ViewInTransit::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getScopedBadgeCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return self::getScopedBadgeCount() > 10 ? 'warning' : 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('resources.in_transits.badge_tooltip');
    }

    // ---------------------------------------------------------------------
    // Badge (§1B.3a canonical implementation)
    // ---------------------------------------------------------------------

    private static function getScopedBadgeCount(): int
    {
        if (self::$badgeCount !== null) {
            return self::$badgeCount;
        }

        if (! self::hasBadgeScope()) {
            return self::$badgeCount = 0;
        }

        $warehouseIds = self::badgeScopedWarehouseIds();

        return self::$badgeCount = static::getModel()::query()
            ->where('status', InTransitStatus::InTransit->value)
            ->whereHas('transferRequisition', function ($q) use ($warehouseIds) {
                $q->whereIn('from_warehouse_id', $warehouseIds)
                    ->orWhereIn('to_warehouse_id', $warehouseIds);
            })
            ->count();
    }
}
