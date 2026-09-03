<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Warehouse;
use App\Traits\DashboardFilterable;
use Filament\Widgets\Widget;
use Livewire\Attributes\Computed;

final class StockByWarehouseWidget extends Widget
{
    use DashboardFilterable;

    protected string $view = 'filament.widgets.stock-by-warehouse';

    protected static ?int $sort = 21;

    protected static ?string $heading = 'Warehouse Inventory Summary';

    protected int|string|array $columnSpan = [
        'sm' => 'full',
        'md' => 'full',
        'lg' => 'full',
    ];

    public static function canView(): bool
    {
        return in_array(auth()->user()?->role?->value, [
            'admin',
            'branch_manager',
            'warehouse_staff',
        ]);
    }

    #[Computed]
    public function warehouses(): array
    {
        $user = auth()->user();
        $warehouseIds = $this->getFilterWarehouseIds($user);

        $query = Warehouse::query()
            ->select('warehouses.*')
            ->selectRaw('(SELECT COALESCE(SUM(ws.on_hand_quantity), 0) FROM warehouse_stock ws WHERE ws.warehouse_id = warehouses.id) as total_quantity')
            ->selectRaw('(SELECT COUNT(DISTINCT pv.product_id) FROM warehouse_stock ws JOIN product_variants pv ON pv.id = ws.variant_id WHERE ws.warehouse_id = warehouses.id) as product_count')
            ->selectRaw('(SELECT COUNT(*) FROM stock_movements sm WHERE sm.warehouse_id = warehouses.id) as total_movements');

        if ($warehouseIds !== null) {
            $query->whereIn('warehouses.id', $warehouseIds);
        }

        return $query->get()
            ->map(fn (Warehouse $warehouse) => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'location' => $warehouse->location,
                'is_active' => $warehouse->is_active,
                'total_quantity' => (int) $warehouse->total_quantity,
                'product_count' => (int) $warehouse->product_count,
                'total_movements' => (int) $warehouse->total_movements,
            ])
            ->toArray();
    }
}
