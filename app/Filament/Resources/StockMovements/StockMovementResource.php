<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements;

use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Resources\StockMovements\Tables\StockMovementsTable;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static UnitEnum|string|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'reference_code';

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'reference_code',
            'variant.product.name',
            'warehouse.name',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['variant', 'warehouse', 'creator']);

        $user = auth()->user();

        if (! $user->isAdmin()) {
            $warehouseIds = $user->warehouses()->pluck('warehouses.id');

            $query->whereIn('stock_movements.warehouse_id', $warehouseIds);
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return StockMovementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockMovements::route('/'),
        ];
    }
}
