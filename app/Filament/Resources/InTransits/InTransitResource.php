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

class InTransitResource extends Resource
{
    use ScopesNavigationBadges;

    protected static ?string $model = InTransit::class;

    protected static string|UnitEnum|null $navigationGroup = 'OPERATIONS';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Truck;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getNavigationBadge(): ?string
    {
        $count = self::getScopedBadgeCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('resources.in_transits.badge_tooltip');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
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
            ->with(['transferRequisition', 'item', 'productVariant']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInTransits::route('/'),
            'view' => ViewInTransit::route('/{record}'),
        ];
    }

    private static function getScopedBadgeCount(): int
    {
        if (self::$badgeCount !== null) {
            return self::$badgeCount;
        }

        if (! self::hasBadgeScope()) {
            return self::$badgeCount = 0;
        }

        // In-transit rows are scoped by the warehouses of their parent
        // requisition. Both endpoints participate because the cargo is in
        // motion between them.
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
