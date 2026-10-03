<?php

declare(strict_types=1);

namespace App\Filament\Resources\LossLedgers;

use App\Filament\Resources\LossLedgers\Pages\ListLossLedgers;
use App\Filament\Resources\LossLedgers\Pages\ViewLossLedger;
use App\Filament\Resources\LossLedgers\Schemas\LossLedgerInfolist;
use App\Filament\Resources\LossLedgers\Tables\LossLedgersTable;
use App\Models\LossLedger;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * LossLedger resource — §18.1a canonical class.
 *
 * Read-only append-only ledger. No navigation badge. Warehouse scope
 * applied at query level per §20.1.
 */
class LossLedgerResource extends Resource
{
    protected static ?string $model = LossLedger::class;

    protected static string|UnitEnum|null $navigationGroup = 'AUDIT LEDGERS';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ExclamationTriangle;

    public static function getModelLabel(): string
    {
        return __('resources.loss_ledgers.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.loss_ledgers.model.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.loss_ledgers.navigation.label');
    }

    public static function table(Table $table): Table
    {
        return LossLedgersTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LossLedgerInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['transferRequisition', 'transferRequisitionItem', 'productVariant', 'warehouse', 'recordedBy'])
            ->when(
                ! auth()->user()->isAdmin() && ! auth()->user()->isAuditor(),
                function (Builder $q) {
                    $ids = auth()->user()->warehouses()->pluck('id')->all();
                    $q->whereIn('warehouse_id', $ids);
                }
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLossLedgers::route('/'),
            'view' => ViewLossLedger::route('/{record}'),
        ];
    }
}
