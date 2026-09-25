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

class TransferRequisitionResource extends Resource
{
    use ScopesNavigationBadges;

    protected static ?string $model = TransferRequisition::class;

    protected static string|UnitEnum|null $navigationGroup = 'OPERATIONS';

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static BackedEnum|string|null $activeNavigationIcon = Heroicon::ArrowsRightLeft;

    protected static ?string $recordTitleAttribute = 'reference_code';

    protected static ?int $navigationSort = 1;

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

    public static function form(Schema $schema): Schema
    {
        return TransferRequisitionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TransferRequisitionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransferRequisitionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['fromWarehouse', 'toWarehouse', 'items.productVariant', 'requestedBy', 'approvedBy', 'items'])
            ->when(
                ! auth()->user()?->can('viewAdminReview', TransferRequisition::class),
                function (Builder $query) {
                    $warehouseIds = auth()->user()?->warehouses()->pluck('warehouses.id')->toArray() ?? [];
                    $query->where(function ($q) use ($warehouseIds) {
                        $q->whereIn('from_warehouse_id', $warehouseIds)
                            ->orWhereIn('to_warehouse_id', $warehouseIds);
                    });
                }
            );
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

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
