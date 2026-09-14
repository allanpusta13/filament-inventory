<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitionItemRevisions;

use App\Filament\Resources\TransferRequisitionItemRevisions\Pages\ListTransferRequisitionItemRevisions;
use App\Filament\Resources\TransferRequisitionItemRevisions\Pages\ViewTransferRequisitionItemRevision;
use App\Filament\Resources\TransferRequisitionItemRevisions\Schemas\TransferRequisitionItemRevisionInfolist;
use App\Filament\Resources\TransferRequisitionItemRevisions\Tables\TransferRequisitionItemRevisionsTable;
use App\Models\TransferRequisitionItemRevision;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class TransferRequisitionItemRevisionResource extends Resource
{
    protected static ?string $model = TransferRequisitionItemRevision::class;

    protected static string|UnitEnum|null $navigationGroup = 'OPERATIONS';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return TransferRequisitionItemRevisionsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TransferRequisitionItemRevisionInfolist::configure($schema);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['transferRequisitionItem', 'transferRequisitionItem.productVariant', 'user', 'productVariant', 'substituteProductVariant']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransferRequisitionItemRevisions::route('/'),
            'view' => ViewTransferRequisitionItemRevision::route('/{record}'),
        ];
    }
}
