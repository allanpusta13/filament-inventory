<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Warehouse;
use Filament\Widgets\Widget;

final class StockByWarehouseWidget extends Widget
{
    public ?array $warehouses = [];

    protected string $view = 'filament.widgets.stock-by-warehouse';

    protected static ?int $sort = 15;

    protected static ?string $heading = 'Warehouse Inventory Summary';

    protected int|string|array $columnSpan = [
        'sm' => 'full',
        'md' => 'full',
        'lg' => 'full',
    ];

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->loadWarehouses();
    }

    public function loadWarehouses(): void
    {
        $user = auth()->user();
        $isAdmin = $user->isAdmin();

        $query = Warehouse::query()
            ->select('warehouses.*')
            ->withCount(['stockMovements as total_movements']);

        if (! $isAdmin) {
            $warehouseIds = $user->warehouses()->pluck('warehouses.id');
            $query->whereIn('warehouses.id', $warehouseIds);
        }

        $warehouses = $query->get();

        $this->warehouses = $warehouses->map(function (Warehouse $warehouse) {
            $totalQuantity = $warehouse->stockMovements()->sum('quantity');
            $productCount = $warehouse->stockMovements()
                ->select('product_id')
                ->distinct()
                ->count('product_id');

            return [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'location' => $warehouse->location,
                'is_active' => $warehouse->is_active,
                'total_quantity' => $totalQuantity,
                'product_count' => $productCount,
                'total_movements' => $warehouse->total_movements,
            ];
        })->toArray();
    }
}
