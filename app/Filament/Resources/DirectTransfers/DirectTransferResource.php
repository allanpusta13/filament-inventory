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

/**
 * DirectTransfer resource — §7C.6 canonical class.
 *
 * Bound to `DirectTransfer` (the header), not `StockMovement`. Fire-
 * and-forget: no edit page (A11). Both-endpoint warehouse scope for
 * non-admin/non-auditor users.
 *
 * No navigation badge — §1B.2 declares badges only for the four
 * badge-bearing resources.
 */
class DirectTransferResource extends Resource
{
    protected static ?string $model = DirectTransfer::class;

    protected static string|UnitEnum|null $navigationGroup = 'OPERATIONS';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'reference_code';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ArrowPath;

    public static function getModelLabel(): string
    {
        return __('resources.direct_transfers.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.direct_transfers.model.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.direct_transfers.navigation.label');
    }

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
            ->withCount('items')
            ->when(
                ! auth()->user()->isAdmin() && ! auth()->user()->isAuditor(),
                function (Builder $q) {
                    // Intentional AND-scope (§20.1): a direct transfer moves
                    // stock between two warehouses, so a non-privileged user
                    // must be assigned to BOTH endpoints to list it. A
                    // single-warehouse user therefore sees zero direct
                    // transfers by design — not a bug.
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
