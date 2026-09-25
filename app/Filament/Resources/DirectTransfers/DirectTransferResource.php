<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectTransfers;

use App\Filament\Resources\DirectTransfers\Pages\CreateDirectTransfer;
use App\Filament\Resources\DirectTransfers\Pages\ListDirectTransfers;
use App\Filament\Resources\DirectTransfers\Pages\ViewDirectTransfer;
use App\Filament\Resources\DirectTransfers\Schemas\DirectTransferForm;
use App\Filament\Resources\DirectTransfers\Schemas\DirectTransferInfolist;
use App\Filament\Resources\DirectTransfers\Tables\DirectTransfersTable;
use App\Models\DirectTransfer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DirectTransferResource extends Resource
{
    protected static ?string $model = DirectTransfer::class;

    protected static string|UnitEnum|null $navigationGroup = 'OPERATIONS';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'reference_code';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ArrowPath;

    public static function form(Schema $schema): Schema
    {
        return DirectTransferForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DirectTransfersTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DirectTransferInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['fromWarehouse', 'toWarehouse', 'transferredBy', 'items.productVariant'])
            ->when(
                ! auth()->user()->isAdmin() && ! auth()->user()->isAuditor(),
                function (Builder $q) {
                    $ids = auth()->user()->warehouses()->pluck('id')->all();
                    $q->whereIn('from_warehouse_id', $ids)
                        ->whereIn('to_warehouse_id', $ids);
                }
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDirectTransfers::route('/'),
            'create' => CreateDirectTransfer::route('/create'),
            'view' => ViewDirectTransfer::route('/{record}'),
        ];
    }
}
