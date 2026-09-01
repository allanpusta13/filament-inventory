<?php

declare(strict_types=1);

namespace App\Filament\Resources\CurrentStock;

use App\Filament\Resources\CurrentStock\Pages\ListCurrentStock;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class CurrentStockResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static UnitEnum|string|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 21;

    protected static ?string $navigationLabel = 'Current Stock';

    protected static ?string $slug = 'current-stock';

    protected static ?string $recordTitleAttribute = 'variant_id';

    public static function getEloquentQuery(): Builder
    {
        $query = StockMovement::query()
            ->selectRaw('MIN(stock_movements.id) as id, stock_movements.variant_id, stock_movements.warehouse_id, SUM(stock_movements.quantity) as quantity')
            ->join('product_variants', 'product_variants.id', '=', 'stock_movements.variant_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_movements.warehouse_id')
            ->groupBy('stock_movements.variant_id', 'stock_movements.warehouse_id')
            ->havingRaw('SUM(stock_movements.quantity) != 0');

        $user = auth()->user();

        if (! $user->isAdmin()) {
            $warehouseIds = $user->warehouses()->pluck('warehouses.id');
            $query->whereIn('stock_movements.warehouse_id', $warehouseIds);
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return Tables\CurrentStockTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCurrentStock::route('/'),
        ];
    }
}
