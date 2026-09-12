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
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class InTransitResource extends Resource
{
    protected static ?string $model = InTransit::class;

    protected static string|UnitEnum|null $navigationGroup = 'OPERATIONS';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'id';

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
}
