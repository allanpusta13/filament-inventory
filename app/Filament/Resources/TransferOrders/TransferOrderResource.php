<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferOrders;

use App\Filament\Resources\TransferOrders\Pages\CreateTransferOrder;
use App\Filament\Resources\TransferOrders\Pages\EditTransferOrder;
use App\Filament\Resources\TransferOrders\Pages\ListTransferOrders;
use App\Filament\Resources\TransferOrders\Pages\ViewTransferOrder;
use App\Filament\Resources\TransferOrders\Schemas\TransferOrderForm;
use App\Filament\Resources\TransferOrders\Tables\TransferOrdersTable;
use App\Models\TransferOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class TransferOrderResource extends Resource
{
    protected static ?string $model = TransferOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static UnitEnum|string|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 25;

    protected static ?string $recordTitleAttribute = 'reference_number';

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'reference_number',
            'sender.name',
            'receiver.name',
        ];
    }

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        /** @var TransferOrder $record */
        return $record->isEditable();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        /** @var TransferOrder $record */
        return $record->isEditable();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if (! $user->isAdmin()) {
            $warehouseIds = $user->warehouses()->pluck('warehouses.id');

            $query->where(function (Builder $q) use ($warehouseIds): void {
                $q->whereIn('sender_branch_id', $warehouseIds)
                    ->orWhereIn('receiver_branch_id', $warehouseIds);
            });
        }

        return $query;
    }

    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return TransferOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransferOrdersTable::configure($table);
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
            'index' => ListTransferOrders::route('/'),
            'create' => CreateTransferOrder::route('/create'),
            'view' => ViewTransferOrder::route('/{record}'),
            'edit' => EditTransferOrder::route('/{record}/edit'),
        ];
    }
}
