<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions;

use App\Enums\TransferRequisitionStatus;
use App\Filament\Resources\TransferRequisitions\Pages\CreateTransferRequisition;
use App\Filament\Resources\TransferRequisitions\Pages\EditTransferRequisition;
use App\Filament\Resources\TransferRequisitions\Pages\ListTransferRequisitions;
use App\Filament\Resources\TransferRequisitions\Pages\ViewTransferRequisition;
use App\Filament\Resources\TransferRequisitions\Schemas\TransferRequisitionForm;
use App\Filament\Resources\TransferRequisitions\Schemas\TransferRequisitionInfolist;
use App\Filament\Resources\TransferRequisitions\Tables\TransferRequisitionsTable;
use App\Filament\Support\Concerns\ScopesNavigationBadges;
use App\Models\TransferRequisition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * TransferRequisition resource — §18.1a canonical class.
 *
 * Badge-bearing (§1B.3a). Uses `ScopesNavigationBadges` for the badge
 * count and resolver. Both-endpoint OR-scope for non-admin/non-auditor
 * users per §20.1.
 */
class TransferRequisitionResource extends Resource
{
    use ScopesNavigationBadges;

    protected static ?string $model = TransferRequisition::class;

    protected static string|UnitEnum|null $navigationGroup = 'OPERATIONS';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference_code';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ArrowsRightLeft;

    public static function getModelLabel(): string
    {
        return __('resources.transfer_requisitions.model.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.transfer_requisitions.model.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.transfer_requisitions.navigation.label');
    }

    public static function form(Schema $schema): Schema
    {
        return TransferRequisitionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransferRequisitionsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TransferRequisitionInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'fromWarehouse', 'toWarehouse',
                'requestedBy', 'approvedBy', 'dispatchedBy', 'receivedBy',
                'items.productVariant', 'items.substituteProductVariant',
                'items.revisions', 'items.revisions.user',
            ])
            ->withCount('items')
            ->when(
                ! auth()->user()->isAdmin() && ! auth()->user()->isAuditor(),
                function (Builder $q) {
                    $ids = auth()->user()->warehouses()->pluck('id')->all();
                    $q->where(function (Builder $qq) use ($ids) {
                        $qq->whereIn('from_warehouse_id', $ids)
                            ->orWhereIn('to_warehouse_id', $ids);
                    });
                }
            );
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getScopedBadgeCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return self::getScopedBadgeCount() > 10 ? 'warning' : 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('resources.transfer_requisitions.badge_tooltip');
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

    // ---------------------------------------------------------------------
    // Badge (§1B.3a canonical implementation)
    // ---------------------------------------------------------------------

    private static function getScopedBadgeCount(): int
    {
        if (self::$badgeCount !== null) {
            return self::$badgeCount;
        }

        if (! self::hasBadgeScope()) {
            return self::$badgeCount = 0;
        }

        $warehouseIds = self::badgeScopedWarehouseIds();

        return self::$badgeCount = static::getModel()::query()
            ->where('status', TransferRequisitionStatus::Requested->value)
            ->where(function ($q) use ($warehouseIds) {
                $q->whereIn('from_warehouse_id', $warehouseIds)
                    ->orWhereIn('to_warehouse_id', $warehouseIds);
            })
            ->count();
    }
}
