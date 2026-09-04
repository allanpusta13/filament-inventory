<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions;

use App\Filament\Resources\TransferRequisitions\Pages\CreateTransferRequisition;
use App\Filament\Resources\TransferRequisitions\Pages\EditTransferRequisition;
use App\Filament\Resources\TransferRequisitions\Pages\ListTransferRequisitions;
use App\Filament\Resources\TransferRequisitions\Pages\ViewTransferRequisition;
use App\Filament\Resources\TransferRequisitions\Tables\TransferRequisitionsTable;
use App\Models\TransferRequisition;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
use Illuminate\Database\Eloquent\Builder;

final class TransferRequisitionResource extends Resource
{
    protected static ?string $model = TransferRequisition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static UnitEnum|string|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 25;

    protected static ?string $recordTitleAttribute = 'reference_code';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['fromWarehouse', 'toWarehouse', 'requestedBy', 'items']);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() || $user?->isBranchManager() || $user?->isWarehouseStaff() || false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('from_warehouse_id')
                ->relationship('fromWarehouse', 'name')
                ->required(),
            Select::make('to_warehouse_id')
                ->relationship('toWarehouse', 'name')
                ->required(),
            TextInput::make('notes'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return TransferRequisitionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransferRequisitions::route('/'),
            'create' => CreateTransferRequisition::route('/create'),
            'view' => ViewTransferRequisition::route('/{record}'),
            'edit' => EditTransferRequisition::route('/{record}/edit'),
        ];
    }
}
