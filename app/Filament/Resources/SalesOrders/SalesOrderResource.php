<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesOrders;

use App\Enums\SalesOrderStatus;
use App\Filament\Resources\SalesOrders\Pages\CreateSalesOrder;
use App\Filament\Resources\SalesOrders\Pages\EditSalesOrder;
use App\Filament\Resources\SalesOrders\Pages\ListSalesOrders;
use App\Filament\Resources\SalesOrders\Pages\ViewSalesOrder;
use App\Filament\Resources\SalesOrders\Schemas\SalesOrderForm;
use App\Filament\Resources\SalesOrders\Schemas\SalesOrderInfolist;
use App\Filament\Resources\SalesOrders\Tables\SalesOrdersTable;
use App\Filament\Support\Concerns\ScopesNavigationBadges;
use App\Models\SalesOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * SalesOrder resource — §18.1a canonical class.
 *
 * Badge-bearing (§1B.3a). Badge counts `Confirmed` status; scope via
 * the trait. Warehouse query scope per §20.1.
 */
class SalesOrderResource extends Resource
{
    use ScopesNavigationBadges;

    protected static ?string $model = SalesOrder::class;

    protected static string|UnitEnum|null $navigationGroup = 'SALES';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference_code';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Banknotes;

    public static function getModelLabel(): string
    {
        return __('resources.sales_orders.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.sales_orders.model.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.sales_orders.navigation.label');
    }

    public static function form(Schema $schema): Schema
    {
        return SalesOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesOrdersTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalesOrderInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['customer', 'warehouse', 'orderedBy', 'dispatchedBy', 'items.productVariant'])
            ->withCount('items')
            ->when(
                ! auth()->user()->isAdmin() && ! auth()->user()->isAuditor(),
                function (Builder $q) {
                    $ids = auth()->user()->warehouses()->pluck('id')->all();
                    $q->whereIn('warehouse_id', $ids);
                }
            );
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
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
        return __('resources.sales_orders.badge_tooltip');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesOrders::route('/'),
            'create' => CreateSalesOrder::route('/create'),
            'view' => ViewSalesOrder::route('/{record}'),
            'edit' => EditSalesOrder::route('/{record}/edit'),
        ];
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

        return self::$badgeCount = static::getModel()::query()
            ->where('status', SalesOrderStatus::Confirmed->value)
            ->whereIn('warehouse_id', self::badgeScopedWarehouseIds())
            ->count();
    }
}
