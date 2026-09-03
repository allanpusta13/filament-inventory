<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Traits\DashboardFilterable;
use Filament\Widgets\Widget;
use Livewire\Attributes\Computed;

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

    #[Computed]
    public function getWarehouses(): array
    {
        $user = auth()->user();
        $warehouseIds = $this->getFilterWarehouseIds($user);

        $query = Warehouse::query()
            ->where('is_active', true)
            ->select('warehouses.*')
            ->selectRaw('(SELECT COALESCE(SUM(sm.quantity), 0) FROM stock_movements sm WHERE sm.warehouse_id = warehouses.id) as total_quantity')
            ->selectRaw('(SELECT COUNT(DISTINCT sm.variant_id) FROM stock_movements sm WHERE sm.warehouse_id = warehouses.id) as product_count');

        if ($warehouseIds !== null) {
            $query->whereIn('warehouses.id', $warehouseIds);
        }

        return $query->get()
            ->map(fn (Warehouse $warehouse) => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'location' => $warehouse->location,
                'total_quantity' => max(0, (int) $warehouse->total_quantity),
                'product_count' => (int) $warehouse->product_count,
            ])
            ->toArray();
    }

    #[Computed]
    public function getRecentMutations(): array
    {
        $user = auth()->user();
        $warehouseIds = $this->getFilterWarehouseIds($user);

        $query = StockMovement::with(['variant.product', 'warehouse'])
            ->latest()
            ->limit(10);

        if ($warehouseIds !== null) {
            $query->whereIn('warehouse_id', $warehouseIds);
        }

        return $query->get()->map(fn (StockMovement $m) => [
            'id' => $m->id,
            'product' => $m->variant?->product?->name ?? 'Unknown',
            'warehouse' => $m->warehouse->name ?? 'Unknown',
            'type' => $m->type->value,
            'quantity' => $m->quantity,
            'reference' => $m->reference_code,
            'created_at' => $m->created_at->format('M j, g:i A'),
        ])->toArray();
    }
}
