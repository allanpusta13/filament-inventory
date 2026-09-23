<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions;

use App\Filament\Resources\TransferRequisitions\Pages\CreateTransferRequisition;
use App\Filament\Resources\TransferRequisitions\Pages\ListTransferRequisitions;
use App\Filament\Resources\TransferRequisitions\Pages\ViewTransferRequisition;
use App\Filament\Resources\TransferRequisitions\Schemas\TransferRequisitionInfolist;
use App\Filament\Resources\TransferRequisitions\Tables\TransferRequisitionsTable;
use App\Models\TransferRequisition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class TransferRequisitionResource extends Resource
{
    protected static ?string $model = TransferRequisition::class;

    protected static string|UnitEnum|null $navigationGroup = 'OPERATIONS';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'reference_code';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return TransferRequisitionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransferRequisitionsTable::configure($table);
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
            'index' => ListTransferRequisitions::route('/'),
            'create' => CreateTransferRequisition::route('/create'),
            'view' => ViewTransferRequisition::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['fromWarehouse', 'toWarehouse', 'items.productVariant', 'requestedBy', 'approvedBy', 'items'])
            ->when(! auth()->user()?->isAdmin(), function (Builder $query) {
                $warehouseIds = auth()->user()?->warehouses()->pluck('warehouses.id')->toArray() ?? [];
                $query->where(function ($q) use ($warehouseIds) {
                    $q->whereIn('from_warehouse_id', $warehouseIds)
                        ->orWhereIn('to_warehouse_id', $warehouseIds);
                });
            });
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
