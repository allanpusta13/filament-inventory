<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements;

use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Resources\StockMovements\Schemas\StockMovementInfolist;
use App\Filament\Resources\StockMovements\Tables\StockMovementsTable;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|UnitEnum|null $navigationGroup = 'AUDIT LEDGERS';

    protected static BackedEnum|string|null $navigationIcon = Heroicon::QueueList;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'reference_code';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return StockMovementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockMovementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockMovements::route('/'),
            'view' => Pages\ViewStockMovement::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['productVariant', 'warehouse', 'relatedMovement', 'createdBy']);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
