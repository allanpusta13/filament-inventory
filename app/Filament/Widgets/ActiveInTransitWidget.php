<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\InTransitStatus;
use App\Models\InTransit;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Cache;

class ActiveInTransitWidget extends TableWidget
{
    protected static ?string $heading = 'Active In-Transit';

    protected int|string|array $columnSpan = 'full';

    protected int|string|array $columnSpanFull = 'full';

    public function getActiveInTransits(bool $bypassCache = false)
    {
        $user = auth()->user();
        $firstWarehouseId = optional($user->warehouses->first())?->id;
        $cacheKey = 'active_in_transit_'.$user->id.'_'.$firstWarehouseId;

        if ($bypassCache) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 300, function () use ($user) {
            $warehouseIds = $user->warehouses->pluck('id')->toArray();

            $query = InTransit::whereIn('transfer_requisition_id', function ($query) use ($warehouseIds) {
                $query->from('transfer_requisitions')
                    ->select('id')
                    ->whereIn('from_warehouse_id', $warehouseIds)
                    ->orWhereIn('to_warehouse_id', $warehouseIds);
            })
                ->where('status', '!=', InTransitStatus::Cleared->value)
                ->with(['transferRequisition.fromWarehouse', 'transferRequisition.toWarehouse', 'productVariant'])
                ->latest('dispatched_at');

            $results = $query->get();

            return $results->map(function ($inTransit) {
                $requisition = $inTransit->transferRequisition;

                return [
                    'id' => $inTransit->id,
                    'requisition_ref' => $requisition?->reference_code ?? 'N/A',
                    'variant_sku' => $inTransit->productVariant?->sku ?? 'N/A',
                    'variant_name' => $inTransit->productVariant?->name ?? 'N/A',
                    'from_warehouse' => $requisition?->fromWarehouse?->name ?? 'N/A',
                    'to_warehouse' => $requisition?->toWarehouse?->name ?? 'N/A',
                    'dispatched_base_qty' => $inTransit->dispatched_base_qty,
                    'status' => $inTransit->status,
                    'dispatched_at' => $inTransit->dispatched_at,
                ];
            });
        });
    }

    public function table(Table $table): Table
    {
        $inTransits = $this->getActiveInTransits();

        return $table
            ->query(
                InTransit::whereIn('id', $inTransits->pluck('id'))
                    ->with(['transferRequisition.fromWarehouse', 'transferRequisition.toWarehouse', 'productVariant'])
            )
            ->columns([
                TextColumn::make('transferRequisition.reference_code')
                    ->label('REQUISITION')
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('productVariant.sku')
                    ->label('SKU')
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('productVariant.name')
                    ->label('VARIANT')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('from_warehouse')
                    ->label('FROM')
                    ->badge()
                    ->color('info'),

                TextColumn::make('to_warehouse')
                    ->label('TO')
                    ->badge()
                    ->color('success'),

                TextColumn::make('dispatched_base_qty')
                    ->label('QTY (BASE)')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('STATUS')
                    ->badge(),

                TextColumn::make('dispatched_at')
                    ->label('DISPATCHED')
                    ->since(),
            ])
            ->paginated(false)
            ->defaultSort('dispatched_at', 'desc');
    }
}
