<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrders;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use App\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderInfolist;
use App\Filament\Resources\PurchaseOrders\Tables\PurchaseOrdersTable;
use App\Filament\Support\Concerns\ScopesNavigationBadges;
use App\Models\PurchaseOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * PurchaseOrder resource — §18.1a canonical class.
 *
 * Badge-bearing (§1B.3a). Badge counts `Ordered` status; scope via the
 * trait. Warehouse query scope for non-privileged users per §20.1.
 */
class PurchaseOrderResource extends Resource
{
    use ScopesNavigationBadges;

    protected static ?string $model = PurchaseOrder::class;

    protected static string|UnitEnum|null $navigationGroup = 'PURCHASING';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference_code';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ShoppingCart;

    public static function getModelLabel(): string
    {
        return __('resources.purchase_orders.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.purchase_orders.model.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.purchase_orders.navigation.label');
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseOrdersTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PurchaseOrderInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['supplier', 'warehouse', 'orderedBy', 'receivedBy', 'items.productVariant'])
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
        return __('resources.purchase_orders.badge_tooltip');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'view' => ViewPurchaseOrder::route('/{record}'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
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
            ->where('status', PurchaseOrderStatus::Ordered->value)
            ->whereIn('warehouse_id', self::badgeScopedWarehouseIds())
            ->count();
    }
}
