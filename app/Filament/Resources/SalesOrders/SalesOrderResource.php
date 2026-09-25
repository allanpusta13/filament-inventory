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

class SalesOrderResource extends Resource
{
    use ScopesNavigationBadges;

    protected static ?string $model = SalesOrder::class;

    protected static string|UnitEnum|null $navigationGroup = 'SALES';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Banknotes;

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

    public static function form(Schema $schema): Schema
    {
        return SalesOrderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalesOrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesOrdersTable::configure($table);
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

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
