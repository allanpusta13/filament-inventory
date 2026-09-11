<?php

declare(strict_types=1);

namespace App\Filament\Resources\InTransits;

use App\Filament\Resources\InTransits\Pages\ListInTransits;
use App\Filament\Resources\InTransits\Pages\ViewInTransit;
use App\Filament\Resources\InTransits\Schemas\InTransitInfolist;
use App\Filament\Resources\InTransits\Tables\InTransitsTable;
use App\Models\InTransit;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class InTransitResource extends Resource
{
    protected static ?string $model = InTransit::class;

    protected static string | \UnitEnum | null $navigationGroup = 'OPERATIONS';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'transfer_requisition_id';

    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema;
    }

    public static function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return InTransitsTable::configure($table);
    }

    public static function infolist(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return InTransitInfolist::configure($schema);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['transferRequisition', 'transferRequisitionItem', 'productVariant']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInTransits::route('/'),
            'view' => ViewInTransit::route('/{record}'),
        ];
    }
}
