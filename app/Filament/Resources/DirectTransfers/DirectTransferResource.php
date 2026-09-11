<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectTransfers;

use App\Filament\Resources\DirectTransfers\Pages\CreateDirectTransfer;
use App\Filament\Resources\DirectTransfers\Pages\ListDirectTransfers;
use App\Filament\Resources\DirectTransfers\Schemas\DirectTransferForm;
use App\Filament\Resources\DirectTransfers\Tables\DirectTransfersTable;
use App\Models\StockMovement;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DirectTransferResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string | \UnitEnum | null $navigationGroup = 'OPERATIONS';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'reference_code';

    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return DirectTransferForm::configure($schema);
    }

    public static function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return DirectTransfersTable::configure($table);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('type', [\App\Enums\StockMovementType::TransferOut, \App\Enums\StockMovementType::TransferIn])
            ->whereNotNull('related_movement_id')
            ->with(['productVariant', 'warehouse']);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListDirectTransfers::route('/'),
            'create' => CreateDirectTransfer::route('/create'),
        ];
    }
}