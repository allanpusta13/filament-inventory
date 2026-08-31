<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferOrders;

use App\Enums\UserRole;
use App\Filament\Resources\TransferOrders\Pages\CreateTransferOrder;
use App\Filament\Resources\TransferOrders\Pages\EditTransferOrder;
use App\Filament\Resources\TransferOrders\Pages\ListTransferOrders;
use App\Filament\Resources\TransferOrders\Pages\ViewTransferOrder;
use App\Filament\Resources\TransferOrders\Schemas\TransferOrderForm;
use App\Filament\Resources\TransferOrders\Tables\TransferOrdersTable;
use App\Models\TransferOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class TransferOrderResource extends Resource
{
    protected static ?string $model = TransferOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static UnitEnum|string|null $navigationGroup = 'Transfers';

    protected static ?string $recordTitleAttribute = 'reference_number';

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'reference_number',
        ];
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->role !== UserRole::Auditor;
    }

    public static function form(Schema $schema): Schema
    {
        return TransferOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransferOrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
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
