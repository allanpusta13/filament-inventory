<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Traits\DashboardFilterable;
use Filament\Widgets\Widget;

final class WarehouseCapacityWidget extends Widget
{
    use DashboardFilterable;

    protected static ?string $heading = 'Warehouse Capacity & Activity';

    protected static ?int $sort = 51;

    protected static ?string $description = 'Stock distribution and recent mutations';

    protected int|string|array $columnSpan = [
        'sm' => 'full',
        'md' => 'full',
        'lg' => 4,
    ];

    protected string $view = 'filament.widgets.warehouse-capacity';

    public function getWarehouses(): array
    {
        $user = auth()->user();
        $warehouseIds = $this->getFilterWarehouseIds($user);

        $query = Warehouse::query()->where('is_active', true);

        if ($warehouseIds !== null) {
            $query->whereIn('id', $warehouseIds);
        }

        return $query->get()->map(function (Warehouse $warehouse) {
            $totalQty = StockMovement::where('warehouse_id', $warehouse->id)->sum('quantity');
            $productCount = StockMovement::where('warehouse_id', $warehouse->id)
                ->distinct('product_id')
                ->count('product_id');

            return [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'location' => $warehouse->location,
                'total_quantity' => max(0, (int) $totalQty),
                'product_count' => $productCount,
            ];
        })->toArray();
    }

    public function getRecentMutations(): array
    {
        $user = auth()->user();
        $warehouseIds = $this->getFilterWarehouseIds($user);

        $query = StockMovement::with(['product', 'warehouse'])
            ->latest()
            ->limit(10);

        if ($warehouseIds !== null) {
            $query->whereIn('warehouse_id', $warehouseIds);
        }

        return $query->get()->map(fn (StockMovement $m) => [
            'id' => $m->id,
            'product' => $m->product->name ?? 'Unknown',
            'warehouse' => $m->warehouse->name ?? 'Unknown',
            'type' => $m->type->value,
            'quantity' => $m->quantity,
            'reference' => $m->reference,
            'created_at' => $m->created_at->format('M j, g:i A'),
        ])->toArray();
    }
}
