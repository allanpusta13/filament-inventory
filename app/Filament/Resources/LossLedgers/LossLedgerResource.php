<?php

declare(strict_types=1);

namespace App\Filament\Resources\LossLedgers;

use App\Filament\Resources\LossLedgers\Pages\ListLossLedgers;
use App\Filament\Resources\LossLedgers\Pages\ViewLossLedger;
use App\Filament\Resources\LossLedgers\Schemas\LossLedgerInfolist;
use App\Filament\Resources\LossLedgers\Tables\LossLedgersTable;
use App\Models\LossLedger;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class LossLedgerResource extends Resource
{
    protected static ?string $model = LossLedger::class;

    protected static string|UnitEnum|null $navigationGroup = 'AUDIT';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return LossLedgersTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LossLedgerInfolist::configure($schema);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['transferRequisition', 'productVariant', 'warehouse', 'recordedBy']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLossLedgers::route('/'),
            'view' => ViewLossLedger::route('/{record}'),
        ];
    }
}
