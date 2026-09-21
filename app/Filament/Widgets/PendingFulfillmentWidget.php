<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\SalesOrder;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget;

class PendingFulfillmentWidget extends TableWidget
{
    protected static ?string $heading = 'Pending Fulfillment';

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation|null
    {
        $user = auth()->user();

        if (! ($user?->isAdmin() ?? false) && ! ($user?->isAuditor() ?? false) && ! ($user?->isBranchManager() ?? false) && ! ($user?->isWarehouseStaff() ?? false)) {
            return SalesOrder::query()->whereRaw('1 = 0');
        }

        $warehouseIds = $user->warehouses->pluck('id')->toArray();

        if (empty($warehouseIds)) {
            return SalesOrder::query()->whereRaw('1 = 0');
        }

        return SalesOrder::whereIn('warehouse_id', $warehouseIds)
            ->whereIn('status', [
                'confirmed',
                'partially_dispatched',
            ])
            ->with(['customer', 'warehouse', 'items.productVariant'])
            ->latest('ordered_at');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('reference_code')
                ->label('REFERENCE')
                ->searchable()
                ->sortable()
                ->copyable()
                ->weight(\Filament\Support\Enums\FontWeight::Bold)
                ->color('primary'),

            TextColumn::make('customer.name')
                ->label('CUSTOMER')
                ->searchable()
                ->sortable(),

            TextColumn::make('warehouse.name')
                ->label('WAREHOUSE')
                ->searchable()
                ->sortable(),

            TextColumn::make('status')
                ->label('STATUS')
                ->badge()
                ->color(fn ($state): string => match ($state) {
                    'confirmed' => 'primary',
                    'partially_dispatched' => 'warning',
                    default => 'gray',
                }),

            TextColumn::make('items_count')
                ->label('ITEMS')
                ->getStateUsing(fn (SalesOrder $record): int => $record->items->count())
                ->sortable(),

            TextColumn::make('total_base_qty')
                ->label('TOTAL BASE QTY')
                ->getStateUsing(fn (SalesOrder $record): int => $record->items->sum('base_qty'))
                ->sortable(),

            TextColumn::make('outstanding_base_qty')
                ->label('OUTSTANDING BASE QTY')
                ->getStateUsing(fn (SalesOrder $record): int => $record->items->sum('outstandingBaseQty'))
                ->sortable()
                ->color('danger'),

            TextColumn::make('ordered_at')
                ->label('ORDERED AT')
                ->dateTime()
                ->sortable(),
        ];
    }
}
